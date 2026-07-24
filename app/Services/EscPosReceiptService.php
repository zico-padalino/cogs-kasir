<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Support\Format;

/**
 * ESC/POS + Thermer — layout identik dengan ReceiptPdfService (posisi, urutan, teks).
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

        $lines = $this->receiptLines($order, $width);
        $thermerJson = $this->linesToThermerJson($lines);
        $shareText = $this->linesToPlainText($lines);

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
     * Mirror exact structure of ReceiptPdfService::build().
     *
     * @return list<array{kind: string, text?: string, left?: string, right?: string, bold: int, align: int, format: int}>
     */
    private function receiptLines(PosOrder $order, int $width): array
    {
        $order->loadMissing(['items.product', 'table', 'cashier']);
        $w = max(24, $width);
        $shop = $this->sanitize((string) config('pos.shop_name', 'Coffee & Kitchen'));
        $lines = [];

        // title — PDF: bold center large
        $lines[] = $this->textLine($shop, bold: 1, align: 1, format: 1);
        // Struk Pembayaran — PDF: center
        $lines[] = $this->textLine('Struk Pembayaran', bold: 0, align: 1, format: 0);
        // spacer
        $lines[] = $this->blankLine();
        // order number — PDF: bold center
        $lines[] = $this->textLine($this->sanitize($order->order_number), bold: 1, align: 1, format: 0);
        // paid_at — PDF: center
        $lines[] = $this->textLine($order->paid_at?->format('d/m/Y H:i') ?? '-', bold: 0, align: 1, format: 0);

        if ($order->order_type) {
            $lines[] = $this->textLine($this->sanitize($order->order_type->label()), bold: 0, align: 1, format: 0);
        }
        if ($order->table) {
            $lines[] = $this->textLine('Meja: '.$this->sanitize($order->table->label), bold: 0, align: 1, format: 0);
        }
        if ($order->customer_note) {
            $lines[] = $this->textLine('Pelanggan: '.$this->sanitize($order->customer_note), bold: 0, align: 1, format: 0);
        }

        $lines[] = $this->blankLine();
        $lines[] = $this->textLine(str_repeat('-', $w), bold: 0, align: 0, format: 0);

        foreach ($order->items as $item) {
            $qty = Format::number($item->quantity, 0);
            $name = $this->sanitize($item->product?->name ?? 'Item');
            // PDF: twoColumns("Nama x qty", harga) — left
            $lines[] = $this->columnsLine($name.' x '.$qty, Format::rupiah($item->line_total), $w, bold: 0, format: 0);

            if ($item->notes) {
                // PDF: "  Catatan: {notes}" left — raw notes sama PDF
                $lines[] = $this->textLine('  Catatan: '.$this->sanitize((string) $item->notes), bold: 0, align: 0, format: 0);
            }
        }

        $lines[] = $this->textLine(str_repeat('-', $w), bold: 0, align: 0, format: 0);

        if ($order->hasDiscount()) {
            $lines[] = $this->columnsLine('Subtotal', Format::rupiah($order->subtotal), $w, bold: 0, format: 0);
            $lines[] = $this->columnsLine('Diskon', '- '.Format::rupiah($order->discount_amount), $w, bold: 0, format: 0);
        }

        // TOTAL — PDF: lebih besar + bold, left two-column
        $lines[] = $this->columnsLine('TOTAL', Format::rupiah($order->total), $w, bold: 1, format: 1);

        $lines[] = $this->textLine('Bayar: '.$this->sanitize($order->payment_method?->label() ?? '-'), bold: 0, align: 0, format: 0);

        if ($order->payment_method?->value === 'cash' && $order->amount_received) {
            $lines[] = $this->textLine('Diterima: '.Format::rupiah($order->amount_received), bold: 0, align: 0, format: 0);
            $lines[] = $this->textLine('Kembalian: '.Format::rupiah($order->change_amount), bold: 0, align: 0, format: 0);
        }

        if ($order->cashierDisplayName() !== '-') {
            $lines[] = $this->textLine('Kasir: '.$this->sanitize($order->cashierDisplayName()), bold: 0, align: 0, format: 0);
        }

        $lines[] = $this->blankLine();
        // Terima kasih — PDF: bold center
        $lines[] = $this->textLine('Terima kasih', bold: 1, align: 1, format: 0);
        $lines[] = $this->blankLine();
        $lines[] = $this->blankLine();

        return $lines;
    }

    /**
     * @return array{kind: string, text: string, bold: int, align: int, format: int}
     */
    private function textLine(string $text, int $bold, int $align, int $format): array
    {
        return [
            'kind' => 'text',
            'text' => $text,
            'bold' => $bold,
            'align' => $align,
            'format' => $format,
        ];
    }

    /**
     * @return array{kind: string, text: string, bold: int, align: int, format: int}
     */
    private function blankLine(): array
    {
        return $this->textLine(' ', bold: 0, align: 0, format: 0);
    }

    /**
     * @return array{kind: string, text: string, bold: int, align: int, format: int}
     */
    private function columnsLine(string $left, string $right, int $width, int $bold, int $format): array
    {
        return [
            'kind' => 'columns',
            'text' => $this->columnsText($left, $right, $width),
            'bold' => $bold,
            'align' => 0,
            'format' => $format,
        ];
    }

    /**
     * @param  list<array{kind: string, text?: string, bold: int, align: int, format: int}>  $lines
     */
    private function linesToThermerJson(array $lines): string
    {
        $entries = [];
        $i = 0;
        foreach ($lines as $line) {
            $entries[(string) $i++] = [
                'type' => 0,
                'content' => $line['text'] ?? ' ',
                'bold' => $line['bold'],
                'align' => $line['align'],
                'format' => $line['format'],
            ];
        }

        return json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param  list<array{kind: string, text?: string, bold: int, align: int, format: int}>  $lines
     */
    private function linesToPlainText(array $lines): string
    {
        $out = [];
        foreach ($lines as $line) {
            $text = $line['text'] ?? '';
            // Spacer PDF → baris kosong (bukan spasi tunggal)
            if (trim($text) === '') {
                $out[] = '';
                continue;
            }
            $out[] = $text;
        }

        return implode("\n", $out)."\n";
    }

    public function build(PosOrder $order, int $width = self::WIDTH_58): string
    {
        $w = max(24, $width);
        $lines = $this->receiptLines($order, $w);

        $out = "\x1B\x40"; // init

        foreach ($lines as $line) {
            $text = $line['text'] ?? ' ';
            $align = (int) $line['align'];
            $bold = (int) $line['bold'] === 1;
            $format = (int) $line['format'];

            // Align: 0 left, 1 center, 2 right
            $out .= match ($align) {
                1 => "\x1B\x61\x01",
                2 => "\x1B\x61\x02",
                default => "\x1B\x61\x00",
            };

            // format 1 = double height (seperti judul/TOTAL PDF yang lebih besar)
            $out .= $format === 1 ? "\x1D\x21\x01" : "\x1D\x21\x00";
            $out .= $bold ? "\x1B\x45\x01" : "\x1B\x45\x00";

            if (trim($text) === '') {
                $out .= "\n";
            } else {
                $out .= $this->line($text, $w);
            }

            $out .= "\x1B\x45\x00";
            $out .= "\x1D\x21\x00";
        }

        $out .= "\x1B\x61\x00";
        $out .= "\x1D\x56\x41\x03";

        return $out;
    }

    private function line(string $text, int $width): string
    {
        return $this->wrap($text, $width)."\n";
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
