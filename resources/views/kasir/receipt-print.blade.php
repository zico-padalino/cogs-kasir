<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            background: #e8e8e8;
            padding: 16px;
        }
        .sheet {
            width: min(100%, 80mm);
            margin: 0 auto;
            background: #fff;
            padding: 10px 8px 16px;
            font-size: 13px;
            line-height: 1.4;
        }
        .center { text-align: center; }
        .shop {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .eyebrow {
            font-size: 11px;
            margin-bottom: 8px;
        }
        .mono { font-family: ui-monospace, Consolas, monospace; font-weight: 700; }
        .meta { margin-top: 2px; font-size: 11px; }
        .sep {
            border: 0;
            border-top: 1px solid #222;
            margin: 8px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 3px 0;
            align-items: flex-start;
        }
        .row .left { flex: 1; min-width: 0; word-break: break-word; }
        .row .right { flex-shrink: 0; white-space: nowrap; font-weight: 600; }
        .note {
            font-size: 10px;
            color: #444;
            margin: 0 0 4px 4px;
        }
        .total-row {
            font-size: 14px;
            font-weight: 700;
            margin-top: 4px;
        }
        .check {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1.5px solid #111;
            margin-right: 6px;
            vertical-align: -1px;
            flex-shrink: 0;
        }
        .item-check {
            display: flex;
            align-items: flex-start;
            gap: 4px;
            margin: 5px 0;
        }
        .footer {
            margin-top: 12px;
            text-align: center;
            font-weight: 700;
        }
        .hint {
            max-width: 320px;
            margin: 12px auto 0;
            text-align: center;
            font-size: 12px;
            color: #555;
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
            /* Biarkan destination & paper size mengikuti printer di laptop/HP. */
            @page {
                margin: 8mm;
            }
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                width: auto;
            }
            .sheet {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 0;
                box-shadow: none;
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

    <div class="sheet" id="receipt-sheet">
        <div class="center">
            <div class="shop">{{ $shopName }}</div>
            <div class="eyebrow">{{ $isKitchen ? 'Struk Dapur' : 'Struk Pembayaran' }}</div>
            <div class="mono">{{ $order->order_number }}</div>
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
                    <span class="check" aria-hidden="true"></span>
                    <div class="left">
                        <div><strong>{{ $name }} x {{ $qty }}</strong></div>
                        @foreach ($noteParts['addon_labels'] as $label)
                            <div class="note">{{ $label }}</div>
                        @endforeach
                        @if ($noteParts['customer'])
                            <div class="note">Catatan: {{ $noteParts['customer'] }}</div>
                        @endif
                    </div>
                </div>
            @else
                <div class="row">
                    <span class="left">{{ $name }} x {{ $qty }}</span>
                    <span class="right">{{ $format::rupiah($item->line_total) }}</span>
                </div>
                @foreach ($noteParts['addon_labels'] as $label)
                    <div class="note">{{ $label }}</div>
                @endforeach
                @if ($noteParts['customer'])
                    <div class="note">Catatan: {{ $noteParts['customer'] }}</div>
                @endif
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
            <div class="row total-row">
                <span class="left">TOTAL</span>
                <span class="right">{{ $format::rupiah($order->total) }}</span>
            </div>
            <div class="meta" style="margin-top:6px">Bayar: {{ $order->payment_method?->label() ?? '-' }}</div>
            @if ($order->payment_method?->value === 'cash' && $order->amount_received)
                <div class="meta">Diterima: {{ $format::rupiah($order->amount_received) }}</div>
                <div class="meta">Kembalian: {{ $format::rupiah($order->change_amount) }}</div>
            @endif
        @endif

        @if ($order->cashierDisplayName() !== '-')
            <div class="meta" style="margin-top:4px">Kasir: {{ $order->cashierDisplayName() }}</div>
        @endif

        @if ($isKitchen)
            <div class="footer" style="font-weight:400;font-size:10px">Ceklis item yang sudah selesai</div>
        @else
            <div class="footer">Terima kasih</div>
        @endif
    </div>

    <div class="hint no-print">
        Pilih printer & ukuran kertas yang tersedia di perangkat ini, lalu cetak.
        <br>
        <button type="button" onclick="window.print()">Cetak lagi</button>
    </div>

    <script>
        window.addEventListener('load', function () {
            // Sedikit delay agar layout sempat render sebelum dialog print.
            setTimeout(function () {
                window.print();
            }, 250);
        });
    </script>
</body>
</html>
