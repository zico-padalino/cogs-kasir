<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Support\Format;
use App\Support\PosItemNotes;

/**
 * ESC/POS receipt + Thermer (mate.bluetoothprint) payload for Bluetooth thermal printers.
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
        $thermerJson = $this->buildThermerJson($order, $width);
        $playStore = (string) config(
            'pos.thermal.thermer_play_store',
            'https://play.google.com/store/apps/details?id=mate.bluetoothprint'
        );
        $encoded = rawurlencode($thermerJson);
        $thermerUrl = 'thermer://?data='.$encoded;
        $intentUrl = 'intent://?data='.$encoded
            .'#Intent;scheme=thermer;package=mate.bluetoothprint;S.browser_fallback_url='
            .rawurlencode($playStore)
            .';end;';

        return [
            'binary' => $binary,
            'base64' => $base64,
            'paper' => $paper,
            'width' => $width,
            'thermer_json' => $thermerJson,
            'thermer_url' => $thermerUrl,
            'intent_url' => $intentUrl,
            'thermer_play_store' => $playStore,
            // Legacy alias (same Thermer intent) — older clients may still read rawbt_* keys.
            'rawbt_url' => $thermerUrl,
            'rawbt_play_store' => $playStore,
        ];
    }

    /**
     * Thermer JSON document (object keyed by entry index) for thermer://?data=…
     *
     * @see https://github.com/tussharmate/ios-thermer-custom-schema
     */
    public function buildThermerJson(PosOrder $order, int $width = self::WIDTH_58): string
    {
        $order->loadMissing(['items.product', 'table', 'cashier']);
        $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');
        $w = max(24, $width);
        $entries = [];
        $i = 0;

        $entries[(string) $i++] = $this->thermerText($this->sanitize($shopName), bold: 1, align: 1, format: 3);
        $entries[(string) $i++] = $this->thermerText($this->joinLines([
            'Struk Pembayaran',
            $this->sanitize($order->order_number),
            $order->paid_at?->format('d/m/Y H:i') ?? '-',
            $order->order_type ? $this->sanitize($order->order_type->label()) : null,
            $order->table ? 'Meja: '.$this->sanitize($order->table->label) : null,
            $order->customer_note ? 'Pelanggan: '.$this->sanitize($order->customer_note) : null,
            str_repeat('-', $w),
        ]), bold: 0, align: 1, format: 0);

        $itemLines = [];
        foreach ($order->items as $item) {
            $qty = Format::number($item->quantity, 0);
            $name = $this->sanitize($item->product?->name ?? 'Item');
            $itemLines[] = $this->columnsText($name.' x '.$qty, Format::rupiah($item->line_total), $w);

            $noteParts = PosItemNotes::split($item->notes);
            if ($noteParts['addon_labels'] !== []) {
                $itemLines[] = '  '.$this->sanitize(implode(' · ', $noteParts['addon_labels']));
            }
            if ($noteParts['customer']) {
                $itemLines[] = '  Catatan: '.$this->sanitize($noteParts['customer']);
            }
        }
        $itemLines[] = str_repeat('-', $w);
        $entries[(string) $i++] = $this->thermerText($this->joinLines($itemLines) ?: '-', bold: 0, align: 0, format: 0);

        $totalLines = [];
        if ($order->hasDiscount()) {
            $totalLines[] = $this->columnsText('Subtotal', Format::rupiah($order->subtotal), $w);
            $totalLines[] = $this->columnsText('Diskon', '- '.Format::rupiah($order->discount_amount), $w);
        }
        $totalLines[] = $this->columnsText('TOTAL', Format::rupiah($order->total), $w);
        $entries[(string) $i++] = $this->thermerText($this->joinLines($totalLines), bold: 1, align: 0, format: 0);

        $footerLines = [
            'Bayar: '.$this->sanitize($order->payment_method?->label() ?? '-'),
        ];
        if ($order->payment_method?->value === 'cash' && $order->amount_received) {
            $footerLines[] = 'Diterima: '.Format::rupiah($order->amount_received);
            $footerLines[] = 'Kembalian: '.Format::rupiah($order->change_amount);
        }
        if ($order->cashierDisplayName() !== '-') {
            $footerLines[] = 'Kasir: '.$this->sanitize($order->cashierDisplayName());
        }
        $entries[(string) $i++] = $this->thermerText($this->joinLines($footerLines), bold: 0, align: 0, format: 0);

        $entries[(string) $i++] = $this->thermerText("Terima kasih<br /><br /><br />", bold: 0, align: 1, format: 0);

        return json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @return array{type: int, content: string, bold: int, align: int, format: int}
     */
    private function thermerText(string $content, int $bold, int $align, int $format): array
    {
        return [
            'type' => 0,
            'content' => $content,
            'bold' => $bold,
            'align' => $align,
            'format' => $format,
        ];
    }

    /**
     * @param  list<string|null>  $lines
     */
    private function joinLines(array $lines): string
    {
        $parts = [];
        foreach ($lines as $line) {
            if ($line === null || $line === '') {
                continue;
            }
            $parts[] = $line;
        }

        return implode('<br />', $parts);
    }

    public function build(PosOrder $order, int $width = self::WIDTH_58): string
    {
        $order->loadMissing(['items.product', 'table', 'cashier']);
        $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');
        $w = max(24, $width);

        $out = '';
        $out .= "\x1B\x40"; // init
        $out .= "\x1B\x61\x01"; // center
        $out .= "\x1B\x45\x01"; // bold
        $out .= $this->line($this->sanitize($shopName), $w);
        $out .= "\x1B\x45\x00";
        $out .= $this->line('Struk Pembayaran', $w);
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
        $out .= $this->separator($w);

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
            }
        }

        $out .= $this->separator($w);

        if ($order->hasDiscount()) {
            $out .= $this->columns('Subtotal', Format::rupiah($order->subtotal), $w);
            $out .= $this->columns('Diskon', '- '.Format::rupiah($order->discount_amount), $w);
        }

        $out .= "\x1B\x45\x01";
        $out .= $this->columns('TOTAL', Format::rupiah($order->total), $w);
        $out .= "\x1B\x45\x00";
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
        $out .= "\x1D\x56\x41\x03"; // partial cut + feed

        return $out;
    }

    private function separator(int $width): string
    {
        return str_repeat('-', $width)."\n";
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

        // Thermer mendukung teks multibahasa; tetap jaga karakter aman untuk layout kolom.
        return preg_replace('/[^\x20-\x7E]+/u', '?', $text) ?? $text;
    }
}
