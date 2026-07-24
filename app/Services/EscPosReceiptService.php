<?php

namespace App\Services;

use App\Models\PosOrder;
use App\Support\Format;
use App\Support\PosItemNotes;

/**
 * ESC/POS + Thermer payload.
 *
 * Thermer:
 * - Deep link: thermer://?data={JSON}  (iOS + Android)
 * - Android share: ACTION_SEND text dengan markup <BAF>
 * - Type 4 = HTML (kontrol ukuran font paling jelas)
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

        $html = $this->buildThermerHtml($order, $width);
        $shareText = $this->buildThermerShareText($order, $width);

        // Satu entry HTML type=4 → font bisa sangat besar, JSON relatif ringkas
        $thermerJson = json_encode([
            '0' => [
                'type' => 4,
                'content' => $html,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        $playStore = (string) config(
            'pos.thermal.thermer_play_store',
            'https://play.google.com/store/apps/details?id=mate.bluetoothprint'
        );

        // Deep link resmi Thermer — JANGAN bungkus intent+package (Chrome akan ke Play Store jika gagal)
        $thermerUrl = 'thermer://?data='.rawurlencode($thermerJson);

        // Intent TANPA package → share sheet (aman, tidak ke Play Store)
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
     * HTML besar untuk Thermer type=4.
     */
    private function buildThermerHtml(PosOrder $order, int $width): string
    {
        $order->loadMissing(['items.product', 'table', 'cashier']);
        $shop = e($this->sanitize((string) config('pos.shop_name', 'Coffee & Kitchen')));
        $w = max(24, $width);

        $lines = [];
        $lines[] = '<div style="width:100%;font-family:monospace,Courier,sans-serif;color:#000">';
        $lines[] = '<div style="text-align:center;font-size:40px;font-weight:900;line-height:1.15;margin:0 0 8px">'.$shop.'</div>';
        $lines[] = '<div style="text-align:center;font-size:26px;font-weight:800;line-height:1.2">STRUK PEMBAYARAN</div>';
        $lines[] = '<div style="text-align:center;font-size:24px;font-weight:700;margin:4px 0 8px">'.e($this->sanitize($order->order_number)).'</div>';
        $lines[] = '<div style="text-align:center;font-size:20px;line-height:1.35">';
        $lines[] = e($order->paid_at?->format('d/m/Y H:i') ?? '-');
        if ($order->order_type) {
            $lines[] = '<br>'.e($this->sanitize($order->order_type->label()));
        }
        if ($order->table) {
            $lines[] = '<br>Meja: '.e($this->sanitize($order->table->label));
        }
        if ($order->customer_note) {
            $lines[] = '<br>Pelanggan: '.e($this->sanitize($order->customer_note));
        }
        $lines[] = '</div>';
        $lines[] = '<div style="border-top:2px solid #000;margin:10px 0"></div>';

        $lines[] = '<div style="font-size:22px;line-height:1.45">';
        foreach ($order->items as $item) {
            $qty = Format::number($item->quantity, 0);
            $name = e($this->sanitize($item->product?->name ?? 'Item'));
            $total = e(Format::rupiah($item->line_total));
            $lines[] = '<div style="display:flex;justify-content:space-between;gap:8px;margin:4px 0">'
                .'<span style="font-weight:700">'.$qty.'x '.$name.'</span>'
                .'<span style="font-weight:700;white-space:nowrap">'.$total.'</span>'
                .'</div>';

            $noteParts = PosItemNotes::split($item->notes);
            if ($noteParts['addon_labels'] !== []) {
                $lines[] = '<div style="font-size:18px;padding-left:8px">+ '
                    .e($this->sanitize(implode(', ', $noteParts['addon_labels'])))
                    .'</div>';
            }
            if ($noteParts['customer']) {
                $lines[] = '<div style="font-size:18px;padding-left:8px">* '
                    .e($this->sanitize($noteParts['customer']))
                    .'</div>';
            }
        }
        $lines[] = '</div>';

        $lines[] = '<div style="border-top:2px solid #000;margin:10px 0"></div>';

        if ($order->hasDiscount()) {
            $lines[] = '<div style="font-size:20px;display:flex;justify-content:space-between"><span>Subtotal</span><span>'
                .e(Format::rupiah($order->subtotal)).'</span></div>';
            $lines[] = '<div style="font-size:20px;display:flex;justify-content:space-between"><span>Diskon</span><span>-'
                .e(Format::rupiah($order->discount_amount)).'</span></div>';
        }

        $lines[] = '<div style="text-align:center;font-size:36px;font-weight:900;line-height:1.2;margin:12px 0">'
            .'TOTAL<br>'.e(Format::rupiah($order->total))
            .'</div>';

        $lines[] = '<div style="font-size:20px;line-height:1.4;text-align:center">';
        $lines[] = 'Bayar: '.e($this->sanitize($order->payment_method?->label() ?? '-'));
        if ($order->payment_method?->value === 'cash' && $order->amount_received) {
            $lines[] = '<br>Diterima: '.e(Format::rupiah($order->amount_received));
            $lines[] = '<br>Kembali: '.e(Format::rupiah($order->change_amount));
        }
        if ($order->cashierDisplayName() !== '-') {
            $lines[] = '<br>Kasir: '.e($this->sanitize($order->cashierDisplayName()));
        }
        $lines[] = '</div>';

        $lines[] = '<div style="text-align:center;font-size:28px;font-weight:800;margin:16px 0 24px">TERIMA KASIH</div>';
        $lines[] = '</div>';

        // $w unused but kept for future paper-aware CSS
        unset($w);

        return implode('', $lines);
    }

    /**
     * Markup <BAF> per baris — cadangan ACTION_SEND / share.
     * F: 0 normal, 1 double H, 2 double H+W, 3 double W
     */
    private function buildThermerShareText(PosOrder $order, int $width): string
    {
        $order->loadMissing(['items.product', 'table', 'cashier']);
        $shop = $this->sanitize((string) config('pos.shop_name', 'Coffee & Kitchen'));
        $w = max(24, $width);
        $wWide = max(12, (int) floor($w / 2));
        $out = '';

        $out .= '<112>'.$shop."\n";
        $out .= "<111>STRUK PEMBAYARAN\n";
        $out .= '<111>'.$this->sanitize($order->order_number)."\n";
        $out .= '<011>'.($order->paid_at?->format('d/m/Y H:i') ?? '-')."\n";
        if ($order->order_type) {
            $out .= '<011>'.$this->sanitize($order->order_type->label())."\n";
        }
        if ($order->table) {
            $out .= '<011>Meja: '.$this->sanitize($order->table->label)."\n";
        }
        if ($order->customer_note) {
            $out .= '<011>Pelanggan: '.$this->sanitize($order->customer_note)."\n";
        }
        $out .= '<010>'.str_repeat('=', $w)."\n";

        foreach ($order->items as $item) {
            $qty = Format::number($item->quantity, 0);
            $name = $this->sanitize($item->product?->name ?? 'Item');
            $out .= '<110>'.$this->columnsText($qty.'x '.$name, Format::rupiah($item->line_total), $w)."\n";

            $noteParts = PosItemNotes::split($item->notes);
            if ($noteParts['addon_labels'] !== []) {
                $out .= '<010> + '.$this->sanitize(implode(', ', $noteParts['addon_labels']))."\n";
            }
            if ($noteParts['customer']) {
                $out .= '<010> * '.$this->sanitize($noteParts['customer'])."\n";
            }
        }

        $out .= '<010>'.str_repeat('=', $w)."\n";

        if ($order->hasDiscount()) {
            $out .= '<010>'.$this->columnsText('Subtotal', Format::rupiah($order->subtotal), $w)."\n";
            $out .= '<010>'.$this->columnsText('Diskon', '-'.Format::rupiah($order->discount_amount), $w)."\n";
        }

        $out .= '<112>'.$this->columnsText('TOTAL', Format::rupiah($order->total), $wWide)."\n";
        $out .= '<011>Bayar: '.$this->sanitize($order->payment_method?->label() ?? '-')."\n";

        if ($order->payment_method?->value === 'cash' && $order->amount_received) {
            $out .= '<011>Diterima: '.Format::rupiah($order->amount_received)."\n";
            $out .= '<011>Kembali: '.Format::rupiah($order->change_amount)."\n";
        }
        if ($order->cashierDisplayName() !== '-') {
            $out .= '<011>Kasir: '.$this->sanitize($order->cashierDisplayName())."\n";
        }

        $out .= "<111>\nTERIMA KASIH\n\n\n";

        return $out;
    }

    public function build(PosOrder $order, int $width = self::WIDTH_58): string
    {
        $order->loadMissing(['items.product', 'table', 'cashier']);
        $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');
        $w = max(24, $width);
        $wWide = max(12, (int) floor($w / 2));

        $out = '';
        $out .= "\x1B\x40";
        $out .= "\x1B\x61\x01";
        $out .= "\x1D\x21\x11";
        $out .= "\x1B\x45\x01";
        $out .= $this->line($this->sanitize($shopName), $wWide);
        $out .= "\x1B\x45\x00";
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

        $out .= "\x1B\x61\x00";
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

        $out .= "\x1B\x61\x01";
        $out .= "\x1D\x21\x11";
        $out .= "\x1B\x45\x01";
        $out .= $this->line('TOTAL', $wWide);
        $out .= $this->line(Format::rupiah($order->total), $wWide);
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
        $out .= "\x1D\x21\x00";
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
