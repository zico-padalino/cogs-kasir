@php
    $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');
    $isKitchen = ($variant ?? 'customer') === 'kitchen';
    $paidAt = $order->paid_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');
    $paper = \App\Support\ShopSettings::normalizeReceiptPaper($paper ?? config('pos.thermal.paper', '58mm'));
    $sheetWidth = $paper === '80mm' ? '80mm' : '58mm';
    $pageSize = match ($paper) {
        '80mm' => '80mm auto',
        '58x210mm' => '58mm 210mm',
        default => '58mm auto',
    };
    $fontBase = $paper === '80mm' ? '11px' : '10px';
    $fontShop = $paper === '80mm' ? '16px' : '14px';

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
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #000;
            background: #e8e8e8;
            padding: 16px;
        }
        .sheet {
            width: {{ $sheetWidth }};
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 8px 5px 14px;
            font-size: {{ $fontBase }};
            line-height: 1.3;
        }
        .center { text-align: center; }
        .shop { font-size: {{ $fontShop }}; font-weight: 700; margin-bottom: 2px; }
        .eyebrow { font-size: {{ $fontBase }}; font-weight: 400; margin-bottom: 6px; }
        .order-no { font-size: calc({{ $fontBase }} + 1px); font-weight: 700; }
        .meta { margin-top: 1px; font-size: {{ $fontBase }}; font-weight: 400; }
        .sep {
            border: 0;
            border-top: 1px solid #000;
            margin: 7px 0;
            height: 0;
        }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.lines td {
            vertical-align: top;
            padding: 2px 0;
            font-size: {{ $fontBase }};
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
            font-size: calc({{ $fontBase }} + 1px);
            padding-top: 4px;
        }
        table.lines td.check {
            width: 14px;
            text-align: right;
            font-weight: 400;
        }
        .box {
            display: inline-block;
            width: 9px;
            height: 9px;
            border: 1.5px solid #000;
            vertical-align: middle;
        }
        .note {
            font-size: calc({{ $fontBase }} - 1px);
            font-weight: 400;
            margin: 0 0 2px 0;
            color: #000;
        }
        .pay-meta {
            margin-top: 4px;
            font-size: {{ $fontBase }};
            font-weight: 400;
            text-align: left;
        }
        .footer {
            margin-top: 10px;
            text-align: center;
            font-weight: 700;
            font-size: calc({{ $fontBase }} + 1px);
        }
        .footer.muted { font-weight: 400; font-size: calc({{ $fontBase }} - 1px); }
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
            @page {
                size: {{ $pageSize }};
                margin: 2mm;
            }
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                width: {{ $sheetWidth }} !important;
                height: auto !important;
                overflow: visible !important;
            }
            .sheet {
                width: 100%;
                max-width: 100%;
                margin: 0;
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
