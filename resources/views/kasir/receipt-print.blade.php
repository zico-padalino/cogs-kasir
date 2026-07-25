<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $order->order_number }}</title>
    <style>
        /*
         * Desain seperti struk PDF, tapi TANPA flex/gap.
         * Printer thermal (POS-58) sering corrupt struk panjang kalau pakai flex.
         */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #000;
            background: #e8e8e8;
            padding: 16px;
        }
        .sheet {
            width: 72mm;
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 10px 8px 18px;
            font-size: 12px;
            line-height: 1.4;
        }
        .center { text-align: center; }
        .shop { font-size: 18px; font-weight: 700; margin-bottom: 2px; }
        .eyebrow { font-size: 12px; font-weight: 400; margin-bottom: 8px; }
        .order-no { font-size: 13px; font-weight: 700; }
        .meta { margin-top: 2px; font-size: 12px; font-weight: 400; }
        .sep {
            border: 0;
            border-top: 1px solid #000;
            margin: 10px 0;
            height: 0;
        }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.lines td {
            vertical-align: top;
            padding: 3px 0;
            font-size: 12px;
            font-weight: 400;
            color: #000;
        }
        table.lines td.l {
            text-align: left;
            width: 62%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.lines td.r {
            text-align: right;
            width: 38%;
            white-space: nowrap;
            font-weight: 700;
        }
        table.lines tr.total td {
            font-weight: 700;
            font-size: 14px;
            padding-top: 5px;
        }
        table.lines td.check {
            width: 16px;
            text-align: right;
            font-weight: 400;
        }
        .box {
            display: inline-block;
            width: 11px;
            height: 11px;
            border: 1.5px solid #000;
            vertical-align: middle;
        }
        .note {
            font-size: 11px;
            font-weight: 400;
            margin: 0 0 3px 0;
            color: #000;
        }
        .pay-meta {
            margin-top: 5px;
            font-size: 12px;
            font-weight: 400;
            text-align: left;
        }
        .footer {
            margin-top: 14px;
            text-align: center;
            font-weight: 700;
            font-size: 13px;
        }
        .footer.muted { font-weight: 400; font-size: 11px; }
        .hint {
            max-width: 280px;
            margin: 12px auto 0;
            text-align: center;
            font-size: 12px;
            color: #555;
            font-family: Arial, Helvetica, sans-serif;
        }
        .hint button {
            margin-top: 8px;
            padding: 8px 14px;
            border: 0;
            border-radius: 8px;
            background: #5c4033;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        @media print {
            /* Satu gulungan kontinu — hindari pecah halaman A4 yang bikin garbage. */
            @page {
                size: auto;
                margin: 3mm;
            }
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                width: auto !important;
                height: auto !important;
                overflow: visible !important;
            }
            .sheet {
                width: 72mm;
                max-width: 100%;
                margin: 0 auto;
                padding: 0;
                box-shadow: none;
            }
            table.lines, table.lines tr, table.lines td {
                page-break-inside: avoid;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
@php
    $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');
    $isKitchen = ($variant ?? 'customer') === 'kitchen';
    $paidAt = $order->paid_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');

    // Karakter aneh sering bikin driver thermal corrupt di struk panjang.
    $clean = static function (?string $text): string {
        $text = (string) ($text ?? '');
        $map = [
            '‘' => "'", '’' => "'", '“' => '"', '”' => '"',
            '–' => '-', '—' => '-', '×' => 'x', '…' => '...',
            '·' => '-', '•' => '-', '●' => '-', ' ' => ' ',
        ];
        $text = strtr($text, $map);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    };
@endphp

    <div class="sheet">
        <div class="center">
            <div class="shop">{{ $clean($shopName) }}</div>
            <div class="eyebrow">{{ $isKitchen ? 'Struk Dapur' : 'Struk Pembayaran' }}</div>
            <div class="order-no">{{ $clean($order->order_number) }}</div>
            <div class="meta">{{ $paidAt }}</div>
            @if ($order->order_type)
                <div class="meta">{{ $clean($order->order_type->label()) }}</div>
            @endif
            @if ($order->table)
                <div class="meta">Meja: {{ $clean($order->table->label) }}</div>
            @endif
            @if ($order->customer_note)
                <div class="meta">Pelanggan: {{ $clean($order->customer_note) }}</div>
            @endif
        </div>

        <hr class="sep">

        <table class="lines" cellspacing="0" cellpadding="0">
            @foreach ($order->items as $item)
                @php
                    $qty = $format::number($item->quantity, 0);
                    $name = $clean($item->product?->name ?? 'Item');
                    $noteParts = \App\Support\PosItemNotes::split($item->notes);
                @endphp

                @if ($isKitchen)
                    <tr>
                        <td class="l">{{ $name }} x {{ $qty }}</td>
                        <td class="check"><span class="box"></span></td>
                    </tr>
                @else
                    <tr>
                        <td class="l">{{ $name }} x {{ $qty }}</td>
                        <td class="r">{{ $format::rupiah($item->line_total) }}</td>
                    </tr>
                @endif

                @foreach ($noteParts['addon_labels'] as $label)
                    <tr>
                        <td class="l" colspan="2"><div class="note">{{ $clean($label) }}</div></td>
                    </tr>
                @endforeach
                @if ($noteParts['customer'])
                    <tr>
                        <td class="l" colspan="2"><div class="note">Catatan: {{ $clean($noteParts['customer']) }}</div></td>
                    </tr>
                @endif
            @endforeach
        </table>

        <hr class="sep">

        @if (! $isKitchen)
            <table class="lines" cellspacing="0" cellpadding="0">
                @if ($order->hasDiscount())
                    <tr>
                        <td class="l">Subtotal</td>
                        <td class="r">{{ $format::rupiah($order->subtotal) }}</td>
                    </tr>
                    <tr>
                        <td class="l">Diskon</td>
                        <td class="r">- {{ $format::rupiah($order->discount_amount) }}</td>
                    </tr>
                @endif
                <tr class="total">
                    <td class="l">TOTAL</td>
                    <td class="r">{{ $format::rupiah($order->total) }}</td>
                </tr>
            </table>
            <div class="pay-meta">Bayar: {{ $clean($order->payment_method?->label() ?? '-') }}</div>
            @if ($order->payment_method?->value === 'cash' && $order->amount_received)
                <div class="pay-meta">Diterima: {{ $format::rupiah($order->amount_received) }}</div>
                <div class="pay-meta">Kembalian: {{ $format::rupiah($order->change_amount) }}</div>
            @endif
        @endif

        @if ($order->cashierDisplayName() !== '-')
            <div class="pay-meta">Kasir: {{ $clean($order->cashierDisplayName()) }}</div>
        @endif

        @if ($isKitchen)
            <div class="footer muted">Ceklis item yang sudah selesai</div>
        @else
            <div class="footer">Terima kasih</div>
        @endif
    </div>

    <div class="hint no-print">
        Pilih printer &amp; kertas di perangkat ini, lalu cetak.
        <br>
        <button type="button" onclick="window.print()">Cetak lagi</button>
    </div>

    <script>
        window.addEventListener('load', function () {
            // Tunggu layout selesai supaya struk panjang tidak corrupt saat print.
            setTimeout(function () { window.print(); }, 400);
        });
    </script>
</body>
</html>
