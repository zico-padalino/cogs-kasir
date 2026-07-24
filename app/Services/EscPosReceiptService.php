<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Support\Format;
use App\Support\PosItemNotes;

/**
 * ESC/POS + Thermer payload.
 * Layout mengikuti ReceiptPdfService, sedikit lebih besar (format 1 = double height).
 * Type 0 = teks (bukan HTML type 4 yang kebesaran).
 */
class EscPosReceiptService
{
    public const WIDTH_58 = 32;

    public const WIDTH_80 = 48;

    public function widthChars(?string $paper = null): int
    {
        $paper = $paper ?: (string) config('pos.thermal.paper', '58mm');

        return $paper === '80mm' ? self::WIDTH_80 : self::WIDTH_58;
    }

    /**
     * @return array{
     *     binary: string,
     *     base64: string,
     *     paper: string,
     *     width: int,
     *     thermer_url: string,
     *     intent_url: string,
     *     thermer_share_text: string,
     *     thermer_play_store: string,
     *     thermer_json: string,
     *     rawbt_url: string
     * }
     */
    public function payload(PosOrder $order, ?string $paper = null): array
    {
        $paper = $paper === '80mm' ? '80mm' : '58mm';
        $width = $paper === '80mm' ? self::WIDTH_80 : self::WIDTH_58;
        $binary = $this->build($order, $width);
        $base64 = base64_encode($binary);

        $blocks = $this->pdfLikeBlocks($order, $width);
        $thermerJson = $this->blocksToThermerJson($blocks);
        // Share: teks bersih seperti PDF (tanpa tag <BAF> / HTML) agar tidak jelek
        $shareText = $this->blocksToPlainText($blocks);

        $playStore = (string) config(
            'pos.thermal.thermer_play_store',
            'https://play.google.com/store/apps/details?id=mate.bluetoothprint'
        );

        $thermerUrl = 'thermer://?data='.rawurlencode($thermerJson);
        $intentUrl = 'intent:#Intent;action=android.intent.action.SEND;type=text/plain;'
            .'S.android.intent.extra.TEXT='.rawurlencode($shareText)
            .';end;';

        return [
            'binary' => $binary,
            'base64' => $base64,
            'paper' => $paper,
            'width' => $width,
            'thermer_json' => $thermerJson,
            'thermer_share_text' => $shareText,
            'thermer_url' => $thermerUrl,
            'intent_url' => $intentUrl,
            'thermer_play_store' => $playStore,
            'rawbt_url' => $thermerUrl,
            'rawbt_play_store' => $playStore,
        ];
    }

    /**
     * Susunan sama ReceiptPdfService, ukuran sedikit lebih besar dari PDF.
     *
     * format: 0 normal · 1 double height (agak besar, mirip PDF diperbesar)
     *
     * @return list<array{content: string, bold: int, align: int, format: int}>
     */
    private function pdfLikeBlocks(PosOrder $order, int $width): array
    {
        $order->loadMissing(['items.product', 'table', 'cashier']);
        $shop = $this->sanitize((string) config('pos.shop_name', 'Coffee & Kitchen'));
        $w = max(24, $width);
        $blocks = [];

        // Nama toko — bold + center + double height (lebih besar dari PDF, tidak double-width)
        $blocks[] = ['content' => $shop, 'bold' => 1, 'align' => 1, 'format' => 1];
        $blocks[] = ['content' => 'Struk Pembayaran', 'bold' => 0, 'align' => 1, 'format' => 0];
        $blocks[] = ['content' => $this->sanitize($order->order_number), 'bold' => 1, 'align' => 1, 'format' => 0];

        $meta = [$order->paid_at?->format('d/m/Y H:i') ?? '-'];
        if ($order->order_type) {
            $meta[] = $this->sanitize($order->order_type->label());
        }
        if ($order->table) {
            $meta[] = 'Meja: '.$this->sanitize($order->table->label);
        }
        if ($order->customer_note) {
            $meta[] = 'Pelanggan: '.$this->sanitize($order->customer_note);
        }
        $blocks[] = [
            'content' => implode("\n", $meta),
            'bold' => 0,
            'align' => 1,
            'format' => 0,
        ];

        $blocks[] = ['content' => str_repeat('-', $w), 'bold' => 0, 'align' => 0, 'format' => 0];

        $itemLines = [];
        foreach ($order->items as $item) {
            $qty = Format::number($item->quantity, 0);
            $name = $this->sanitize($item->product?->name ?? 'Item');
            // Sama PDF: "Nama x qty" .... harga
            $itemLines[] = $this->columnsText($name.' x '.$qty, Format::rupiah($item->line_total), $w);

            $noteParts = PosItemNotes::split($item->notes);
            if ($noteParts['addon_labels'] !== []) {
                $itemLines[] = '  '.$this->sanitize(implode(' · ', $noteParts['addon_labels']));
            }
            if ($noteParts['customer']) {
                $itemLines[] = '  Catatan: '.$this->sanitize($noteParts['customer']);
            } elseif ($item->notes && $noteParts['addon_labels'] === []) {
                $itemLines[] = '  Catatan: '.$this->sanitize((string) $item->notes);
            }
        }
        $blocks[] = [
            'content' => implode("\n", $itemLines) ?: '-',
            'bold' => 0,
            'align' => 0,
            'format' => 0,
        ];

        $blocks[] = ['content' => str_repeat('-', $w), 'bold' => 0, 'align' => 0, 'format' => 0];

        $totalLines = [];
        if ($order->hasDiscount()) {
            $totalLines[] = $this->columnsText('Subtotal', Format::rupiah($order->subtotal), $w);
            $totalLines[] = $this->columnsText('Diskon', '- '.Format::rupiah($order->discount_amount), $w);
        }
        $totalLines[] = $this->columnsText('TOTAL', Format::rupiah($order->total), $w);
        $blocks[] = [
            'content' => implode("\n", $totalLines),
            'bold' => 1,
            'align' => 0,
            'format' => 1, // TOTAL sedikit lebih besar dari PDF
        ];

        $footer = ['Bayar: '.$this->sanitize($order->payment_method?->label() ?? '-')];
        if ($order->payment_method?->value === 'cash' && $order->amount_received) {
            $footer[] = 'Diterima: '.Format::rupiah($order->amount_received);
            $footer[] = 'Kembalian: '.Format::rupiah($order->change_amount);
        }
        if ($order->cashierDisplayName() !== '-') {
            $footer[] = 'Kasir: '.$this->sanitize($order->cashierDisplayName());
        }
        $blocks[] = [
            'content' => implode("\n", $footer),
            'bold' => 0,
            'align' => 0,
            'format' => 0,
        ];

        $blocks[] = ['content' => "\nTerima kasih\n\n", 'bold' => 0, 'align' => 1, 'format' => 0];

        return $blocks;
    }

    /**
     * @param  list<array{content: string, bold: int, align: int, format: int}>  $blocks
     */
    private function blocksToThermerJson(array $blocks): string
    {
        $entries = [];
        foreach ($blocks as $index => $block) {
            $entries[(string) $index] = [
                'type' => 0,
                'content' => str_replace("\n", '<br />', $block['content']),
                'bold' => $block['bold'],
                'align' => $block['align'],
                'format' => $block['format'],
            ];
        }

        return json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * Teks polos untuk "Bagikan ke Thermer" — tampilan seperti struk PDF.
     *
     * @param  list<array{content: string, bold: int, align: int, format: int}>  $blocks
     */
    private function blocksToPlainText(array $blocks): string
    {
        $lines = [];
        foreach ($blocks as $block) {
            $lines[] = rtrim($block['content'], "\n");
        }

        return implode("\n", $lines)."\n";
    }

    public function build(PosOrder $order, int $width = self::WIDTH_58): string
    {
        $order->loadMissing(['items.product', 'table', 'cashier']);
        $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');
        $w = max(24, $width);

        $out = '';
        $out .= "\x1B\x40";
        $out .= "\x1B\x61\x01";

        // Double height saja (bukan double width) — mirip PDF diperbesar
        $out .= "\x1D\x21\x01";
        $out .= "\x1B\x45\x01";
        $out .= $this->line($this->sanitize($shopName), $w);
        $out .= "\x1B\x45\x00";
        $out .= "\x1D\x21\x00";

        $out .= $this->line('Struk Pembayaran', $w);
        $out .= "\x1B\x45\x01";
        $out .= $this->line($this->sanitize($order->order_number), $w);
        $out .= "\x1B\x45\x00";
        $out .= $this->line($order->paid_at?->format('d/m/Y H:i') ?? '-', $w);

        if ($order->order_type) {
            $out .= $this->line($this->sanitize($order->order_type->label()), $w);
        }
        if ($order->table) {
            $out .= $this->line('Meja: '.$this->sanitize($order->table->label), $w);
        }
        if ($order->customer_note) {
            $out .= $this->line('Pelanggan: '.$this->sanitize($order->customer_note), $w);
        }

        $out .= "\x1B\x61\x00";
        $out .= str_repeat('-', $w)."\n";

        foreach ($order->items as $item) {
            $qty = Format::number($item->quantity, 0);
            $name = $this->sanitize($item->product?->name ?? 'Item');
            $out .= $this->columns($name.' x '.$qty, Format::rupiah($item->line_total), $w);

            $noteParts = PosItemNotes::split($item->notes);
            if ($noteParts['addon_labels'] !== []) {
                $out .= $this->line('  '.$this->sanitize(implode(' · ', $noteParts['addon_labels'])), $w);
            }
            if ($noteParts['customer']) {
                $out .= $this->line('  Catatan: '.$this->sanitize($noteParts['customer']), $w);
            } elseif ($item->notes && $noteParts['addon_labels'] === []) {
                $out .= $this->line('  Catatan: '.$this->sanitize((string) $item->notes), $w);
            }
        }

        $out .= str_repeat('-', $w)."\n";

        if ($order->hasDiscount()) {
            $out .= $this->columns('Subtotal', Format::rupiah($order->subtotal), $w);
            $out .= $this->columns('Diskon', '- '.Format::rupiah($order->discount_amount), $w);
        }

        $out .= "\x1D\x21\x01";
        $out .= "\x1B\x45\x01";
        $out .= $this->columns('TOTAL', Format::rupiah($order->total), $w);
        $out .= "\x1B\x45\x00";
        $out .= "\x1D\x21\x00";

        $out .= $this->line('Bayar: '.$this->sanitize($order->payment_method?->label() ?? '-'), $w);

        if ($order->payment_method?->value === 'cash' && $order->amount_received) {
            $out .= $this->line('Diterima: '.Format::rupiah($order->amount_received), $w);
            $out .= $this->line('Kembalian: '.Format::rupiah($order->change_amount), $w);
        }

        if ($order->cashierDisplayName() !== '-') {
            $out .= $this->line('Kasir: '.$this->sanitize($order->cashierDisplayName()), $w);
        }

        $out .= "\n";
        $out .= "\x1B\x61\x01";
        $out .= $this->line('Terima kasih', $w);
        $out .= "\n\n\n";
        $out .= "\x1D\x56\x41\x03";

        return $out;
    }

    private function line(string $text, int $width): string
    {
        return $this->wrap($text, $width)."\n";
    }

    private function columns(string $left, string $right, int $width): string
    {
        return $this->columnsText($left, $right, $width)."\n";
    }

    private function columnsText(string $left, string $right, int $width): string
    {
        $left = $this->sanitize($left);
        $right = $this->sanitize($right);
        $rightLen = strlen($right);
        $maxLeft = max(1, $width - $rightLen - 1);

        if (strlen($left) > $maxLeft) {
            $left = substr($left, 0, max(1, $maxLeft - 1)).'.';
        }

        $pad = $width - strlen($left) - $rightLen;

        return $left.str_repeat(' ', max(1, $pad)).$right;
    }

    private function wrap(string $text, int $width): string
    {
        $text = $this->sanitize($text);
        if (strlen($text) <= $width) {
            return $text;
        }

        return implode("\n", str_split($text, $width));
    }

    private function sanitize(string $text): string
    {
        $map = [
            '‘' => "'", '’' => "'", '“' => '"', '”' => '"',
            '–' => '-', '—' => '-', '×' => 'x', '…' => '...',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a',
            'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ];
        $text = strtr($text, $map);

        return preg_replace('/[^\x20-\x7E]+/u', '?', $text) ?? $text;
    }
}
