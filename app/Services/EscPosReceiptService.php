<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Support\Format;
use App\Support\PosItemNotes;

/**
 * ESC/POS receipt + Thermer (mate.bluetoothprint) payload for Bluetooth thermal printers.
 *
 * Thermer Android markup: <BAF>text
 *   B = bold (0/1), A = align (0 left / 1 center / 2 right),
 *   F = format (0 normal / 1 double height / 2 double H+W / 3 double width / 4 small)
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
        $blocks = $this->thermerBlocks($order, $width);
        $thermerJson = $this->blocksToThermerJson($blocks);
        $shareText = $this->blocksToThermerShareText($blocks);
        $playStore = (string) config(
            'pos.thermal.thermer_play_store',
            'https://play.google.com/store/apps/details?id=mate.bluetoothprint'
        );

        // Deep link (iOS + Android jika scheme terdaftar)
        $thermerUrl = 'thermer://?data='.rawurlencode($thermerJson);

        // Android one-tap: ACTION_SEND langsung ke package Thermer — tanpa Play Store fallback.
        $intentUrl = 'intent:#Intent;action=android.intent.action.SEND;type=text/plain;'
            .'package=mate.bluetoothprint;'
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
            'rawbt_url' => $intentUrl,
            'rawbt_play_store' => $playStore,
        ];
    }

    /**
     * Struk besar: double height/width untuk nama toko, item, dan TOTAL.
     *
     * @return list<array{content: string, bold: int, align: int, format: int}>
     */
    private function thermerBlocks(PosOrder $order, int $width): array
    {
        $order->loadMissing(['items.product', 'table', 'cashier']);
        $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');
        // Format 1 = double height → tetap full width. Format 2/3 = double width → setengah kolom.
        $w = max(24, $width);
        $wWide = max(12, (int) floor($w / 2));
        $blocks = [];

        // Nama toko — paling besar (double height + width)
        $blocks[] = [
            'content' => $this->sanitize($shopName),
            'bold' => 1,
            'align' => 1,
            'format' => 2,
        ];

        // Judul + nomor — double height
        $blocks[] = [
            'content' => "STRUK PEMBAYARAN\n".$this->sanitize($order->order_number),
            'bold' => 1,
            'align' => 1,
            'format' => 1,
        ];

        $meta = [
            $order->paid_at?->format('d/m/Y H:i') ?? '-',
            $order->order_type ? $this->sanitize($order->order_type->label()) : null,
            $order->table ? 'Meja: '.$this->sanitize($order->table->label) : null,
            $order->customer_note ? 'Pelanggan: '.$this->sanitize($order->customer_note) : null,
        ];
        $blocks[] = [
            'content' => $this->joinLines($meta, "\n")."\n".str_repeat('=', $w),
            'bold' => 0,
            'align' => 1,
            'format' => 1,
        ];

        // Item — double height agar mudah dibaca
        $itemLines = [];
        foreach ($order->items as $item) {
            $qty = Format::number($item->quantity, 0);
            $name = $this->sanitize($item->product?->name ?? 'Item');
            $itemLines[] = $this->columnsText($qty.'x '.$name, Format::rupiah($item->line_total), $w);

            $noteParts = PosItemNotes::split($item->notes);
            if ($noteParts['addon_labels'] !== []) {
                $itemLines[] = ' + '.$this->sanitize(implode(', ', $noteParts['addon_labels']));
            }
            if ($noteParts['customer']) {
                $itemLines[] = ' * '.$this->sanitize($noteParts['customer']);
            }
        }
        $blocks[] = [
            'content' => ($this->joinLines($itemLines, "\n") ?: '-')."\n".str_repeat('=', $w),
            'bold' => 0,
            'align' => 0,
            'format' => 1,
        ];

        // TOTAL + subtotal — besar (≤7 entry Thermer free)
        $totalLines = [];
        if ($order->hasDiscount()) {
            $totalLines[] = $this->columnsText('Subtotal', Format::rupiah($order->subtotal), $wWide);
            $totalLines[] = $this->columnsText('Diskon', '-'.Format::rupiah($order->discount_amount), $wWide);
        }
        $totalLines[] = $this->columnsText('TOTAL', Format::rupiah($order->total), $wWide);
        $blocks[] = [
            'content' => $this->joinLines($totalLines, "\n"),
            'bold' => 1,
            'align' => 1,
            'format' => 2,
        ];

        $footer = [
            'Bayar: '.$this->sanitize($order->payment_method?->label() ?? '-'),
        ];
        if ($order->payment_method?->value === 'cash' && $order->amount_received) {
            $footer[] = 'Diterima: '.Format::rupiah($order->amount_received);
            $footer[] = 'Kembali: '.Format::rupiah($order->change_amount);
        }
        if ($order->cashierDisplayName() !== '-') {
            $footer[] = 'Kasir: '.$this->sanitize($order->cashierDisplayName());
        }
        $footer[] = '';
        $footer[] = 'TERIMA KASIH';
        $footer[] = '';
        $footer[] = '';
        $blocks[] = [
            'content' => $this->joinLines($footer, "\n"),
            'bold' => 1,
            'align' => 1,
            'format' => 1,
        ];

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
     * @param  list<array{content: string, bold: int, align: int, format: int}>  $blocks
     */
    private function blocksToThermerShareText(array $blocks): string
    {
        $out = '';
        foreach ($blocks as $block) {
            $tag = '<'.$block['bold'].$block['align'].$block['format'].'>';
            $out .= $tag.$block['content'];
            if (! str_ends_with($block['content'], "\n")) {
                $out .= "\n";
            }
        }

        return $out;
    }

    /**
     * @param  list<string|null>  $lines
     */
    private function joinLines(array $lines, string $separator = '<br />'): string
    {
        $parts = [];
        foreach ($lines as $line) {
            if ($line === null || $line === '') {
                continue;
            }
            $parts[] = $line;
        }

        return implode($separator, $parts);
    }

    public function build(PosOrder $order, int $width = self::WIDTH_58): string
    {
        $order->loadMissing(['items.product', 'table', 'cashier']);
        $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');
        $w = max(24, $width);
        $wWide = max(12, (int) floor($w / 2));

        $out = '';
        $out .= "\x1B\x40"; // init
        $out .= "\x1B\x61\x01"; // center

        // Nama toko besar (double W+H)
        $out .= "\x1D\x21\x11";
        $out .= "\x1B\x45\x01";
        $out .= $this->line($this->sanitize($shopName), $wWide);
        $out .= "\x1B\x45\x00";

        // Judul + nomor (double height)
        $out .= "\x1D\x21\x01";
        $out .= $this->line('STRUK PEMBAYARAN', $w);
        $out .= $this->line($this->sanitize($order->order_number), $w);
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

        $out .= "\x1B\x61\x00"; // left
        $out .= str_repeat('=', $w)."\n";

        foreach ($order->items as $item) {
            $qty = Format::number($item->quantity, 0);
            $name = $this->sanitize($item->product?->name ?? 'Item');
            $out .= $this->columns($qty.'x '.$name, Format::rupiah($item->line_total), $w);

            $noteParts = PosItemNotes::split($item->notes);
            if ($noteParts['addon_labels'] !== []) {
                $out .= $this->line(' + '.$this->sanitize(implode(', ', $noteParts['addon_labels'])), $w);
            }
            if ($noteParts['customer']) {
                $out .= $this->line(' * '.$this->sanitize($noteParts['customer']), $w);
            }
        }

        $out .= str_repeat('=', $w)."\n";

        if ($order->hasDiscount()) {
            $out .= $this->columns('Subtotal', Format::rupiah($order->subtotal), $w);
            $out .= $this->columns('Diskon', '-'.Format::rupiah($order->discount_amount), $w);
        }

        // TOTAL besar
        $out .= "\x1B\x61\x01";
        $out .= "\x1D\x21\x11";
        $out .= "\x1B\x45\x01";
        $out .= $this->columns('TOTAL', Format::rupiah($order->total), $wWide);
        $out .= "\x1B\x45\x00";
        $out .= "\x1D\x21\x01";
        $out .= "\x1B\x61\x00";

        $out .= $this->line('Bayar: '.$this->sanitize($order->payment_method?->label() ?? '-'), $w);

        if ($order->payment_method?->value === 'cash' && $order->amount_received) {
            $out .= $this->line('Diterima: '.Format::rupiah($order->amount_received), $w);
            $out .= $this->line('Kembali: '.Format::rupiah($order->change_amount), $w);
        }

        if ($order->cashierDisplayName() !== '-') {
            $out .= $this->line('Kasir: '.$this->sanitize($order->cashierDisplayName()), $w);
        }

        $out .= "\n";
        $out .= "\x1B\x61\x01";
        $out .= "\x1B\x45\x01";
        $out .= $this->line('TERIMA KASIH', $w);
        $out .= "\x1B\x45\x00";
        $out .= "\x1D\x21\x00"; // reset size
        $out .= "\n\n\n";
        $out .= "\x1D\x56\x41\x03"; // partial cut + feed

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

        $chunks = str_split($text, $width);

        return implode("\n", $chunks);
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
