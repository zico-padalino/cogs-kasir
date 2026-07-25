<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $order->order_number }}</title>
    <style>
        /* Desain mengikuti ReceiptPdfService / SimplePdf (seperti preview struk). */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Helvetica, Arial, sans-serif;
            color: #111;
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
            line-height: 1.35;
        }
        .center { text-align: center; }
        .shop {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .eyebrow {
            font-size: 12px;
            font-weight: 400;
            margin-bottom: 8px;
        }
        .order-no {
            font-size: 13px;
            font-weight: 700;
        }
        .meta {
            margin-top: 2px;
            font-size: 12px;
            font-weight: 400;
        }
        .sep {
            border: 0;
            border-top: 1px solid #111;
            margin: 10px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 8px;
            margin: 4px 0;
        }
        .row .left {
            flex: 1;
            min-width: 0;
            word-break: break-word;
            font-weight: 400;
        }
        .row .right {
            flex-shrink: 0;
            white-space: nowrap;
            font-weight: 700;
        }
        .row.is-total .left,
        .row.is-total .right {
            font-weight: 700;
            font-size: 14px;
        }
        .note {
            font-size: 11px;
            color: #333;
            margin: 0 0 4px 2px;
            font-weight: 400;
        }
        .item-check {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            margin: 5px 0;
        }
        .item-check .name {
            flex: 1;
            min-width: 0;
            word-break: break-word;
            font-weight: 400;
        }
        .check {
            width: 12px;
            height: 12px;
            border: 1.5px solid #111;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .pay-meta {
            margin-top: 6px;
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
        .footer.muted {
            font-weight: 400;
            font-size: 11px;
        }
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
            @page { margin: 4mm; }
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .sheet {
                width: 72mm;
                margin: 0 auto;
                padding: 0 1mm 4mm;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
@php
    $shopName = config('pos.shop_name', 'Coffee & Kitchen');
    $isKitchen = ($variant ?? 'customer') === 'kitchen';
    $paidAt = $order->paid_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');
@endphp

    <div class="sheet">
        <div class="center">
            <div class="shop">{{ $shopName }}</div>
            <div class="eyebrow">{{ $isKitchen ? 'Struk Dapur' : 'Struk Pembayaran' }}</div>
            <div class="order-no">{{ $order->order_number }}</div>
            <div class="meta">{{ $paidAt }}</div>
            @if ($order->order_type)
                <div class="meta">{{ $order->order_type->label() }}</div>
            @endif
            @if ($order->table)
                <div class="meta">Meja: {{ $order->table->label }}</div>
            @endif
            @if ($order->customer_note)
                <div class="meta">Pelanggan: {{ $order->customer_note }}</div>
            @endif
        </div>

        <hr class="sep">

        @foreach ($order->items as $item)
            @php
                $qty = $format::number($item->quantity, 0);
                $name = $item->product?->name ?? 'Item';
                $noteParts = \App\Support\PosItemNotes::split($item->notes);
            @endphp

            @if ($isKitchen)
                <div class="item-check">
                    <div class="name">{{ $name }} x {{ $qty }}</div>
                    <span class="check" aria-hidden="true"></span>
                </div>
            @else
                <div class="row">
                    <span class="left">{{ $name }} x {{ $qty }}</span>
                    <span class="right">{{ $format::rupiah($item->line_total) }}</span>
                </div>
            @endif

            @foreach ($noteParts['addon_labels'] as $label)
                <div class="note">{{ $label }}</div>
            @endforeach
            @if ($noteParts['customer'])
                <div class="note">Catatan: {{ $noteParts['customer'] }}</div>
            @endif
        @endforeach

        <hr class="sep">

        @if (! $isKitchen)
            @if ($order->hasDiscount())
                <div class="row">
                    <span class="left">Subtotal</span>
                    <span class="right">{{ $format::rupiah($order->subtotal) }}</span>
                </div>
                <div class="row">
                    <span class="left">Diskon</span>
                    <span class="right">- {{ $format::rupiah($order->discount_amount) }}</span>
                </div>
            @endif
            <div class="row is-total">
                <span class="left">TOTAL</span>
                <span class="right">{{ $format::rupiah($order->total) }}</span>
            </div>
            <div class="pay-meta">Bayar: {{ $order->payment_method?->label() ?? '-' }}</div>
            @if ($order->payment_method?->value === 'cash' && $order->amount_received)
                <div class="pay-meta">Diterima: {{ $format::rupiah($order->amount_received) }}</div>
                <div class="pay-meta">Kembalian: {{ $format::rupiah($order->change_amount) }}</div>
            @endif
        @endif

        @if ($order->cashierDisplayName() !== '-')
            <div class="pay-meta">Kasir: {{ $order->cashierDisplayName() }}</div>
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
            setTimeout(function () { window.print(); }, 250);
        });
    </script>
</body>
</html>
