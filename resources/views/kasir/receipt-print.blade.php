@php
    $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');
    $variant = $variant ?? 'customer';
    $isStation = in_array($variant, ['kitchen', 'bar'], true);
    $stationTitle = $variant === 'bar' ? 'Struk Bar' : 'Struk Dapur';
    $emptyStationLabel = $variant === 'bar' ? 'Tidak ada item minuman' : 'Tidak ada item dapur';
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
            width: 58mm;
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 6px 4px 12px;
            font-size: 11px;
            line-height: 1.35;
        }
        .center { text-align: center; }
        .shop {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 2px;
            letter-spacing: -0.02em;
        }
        .eyebrow {
            font-size: 11px;
            font-weight: 400;
            margin-bottom: 6px;
        }
        .order-no {
            font-size: 12px;
            font-weight: 700;
        }
        .meta {
            margin-top: 1px;
            font-size: 11px;
            font-weight: 400;
        }
        .sep {
            border: 0;
            border-top: 1px solid #000;
            margin: 8px 0;
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
            font-size: 11px;
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
            font-size: 12px;
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
            font-size: 10px;
            font-weight: 400;
            margin: 0;
            padding-left: 2px;
            color: #000;
        }
        .pay-meta {
            margin-top: 3px;
            font-size: 11px;
            font-weight: 400;
            text-align: left;
        }
        .footer {
            margin-top: 12px;
            text-align: center;
            font-weight: 700;
            font-size: 12px;
        }
        .footer.muted {
            font-weight: 400;
            font-size: 10px;
        }
        /* Struk dapur: font lebih besar agar mudah dibaca di dapur */
        .sheet.is-kitchen {
            font-size: 14px;
            line-height: 1.4;
            padding: 8px 5px 14px;
        }
        .sheet.is-kitchen .shop { font-size: 18px; }
        .sheet.is-kitchen .eyebrow { font-size: 13px; margin-bottom: 8px; }
        .sheet.is-kitchen .order-no { font-size: 15px; }
        .sheet.is-kitchen .meta { font-size: 13px; }
        .sheet.is-kitchen .sep { margin: 10px 0; }
        .sheet.is-kitchen table.lines td {
            font-size: 14px;
            padding: 4px 0;
            font-weight: 700;
        }
        .sheet.is-kitchen table.lines td.check { width: 18px; }
        .sheet.is-kitchen .box {
            width: 12px;
            height: 12px;
            border-width: 2px;
        }
        .sheet.is-kitchen .note {
            font-size: 13px;
            font-weight: 400;
            padding-left: 4px;
        }
        .sheet.is-kitchen .pay-meta { font-size: 13px; }
        .sheet.is-kitchen .footer.muted { font-size: 12px; }
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
                size: 58mm auto;
                margin: 2mm;
            }
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 58mm !important;
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

    <div class="sheet{{ $isStation ? ' is-kitchen' : '' }}">
        <div class="center">
            <div class="shop">{{ $clean($shopName) }}</div>
            <div class="eyebrow">{{ $isStation ? $stationTitle : 'Struk Pembayaran' }}</div>
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
            @forelse ($order->items as $item)
                @php
                    $qty = $format::number($item->quantity, 0);
                    $name = $clean($item->product?->name ?? 'Item');
                    $noteParts = \App\Support\PosItemNotes::split($item->notes);
                @endphp

                @if ($isStation)
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
            @empty
                @if ($isStation)
                    <tr>
                        <td class="l" colspan="2">{{ $emptyStationLabel }}</td>
                    </tr>
                @endif
            @endforelse
        </table>

        <hr class="sep">

        @if (! $isStation)
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

        @if ($isStation)
            <div class="footer muted">Ceklis item yang sudah selesai</div>
        @else
            <div class="footer">Terima kasih</div>
        @endif
    </div>

    <div class="hint no-print" id="print-hint">
        Pilih printer &amp; kertas di perangkat ini, lalu cetak.
        <br>
        <button type="button" onclick="window.print()">Cetak lagi</button>
    </div>

    <script>
        window.addEventListener('load', function () {
            var isAndroid = /Android/i.test(navigator.userAgent || '');
            var thermalJsonRoute = @json($thermalJsonRoute ?? null);
            var variant = @json($variant ?? 'customer');
            var hint = document.getElementById('print-hint');

            function openThermer(url) {
                var iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = url;
                document.body.appendChild(iframe);
                setTimeout(function () {
                    try { document.body.removeChild(iframe); } catch (e) {}
                }, 1500);
                try { window.location.href = url; } catch (e) {}
            }

            async function printViaThermer() {
                if (! thermalJsonRoute) {
                    if (hint) hint.innerHTML = 'Buka halaman struk, lalu klik Cetak di sana agar Thermer terbuka.';
                    return;
                }
                if (hint) hint.textContent = 'Membuka Thermer…';
                try {
                    var res = await fetch(
                        thermalJsonRoute + '?variant=' + encodeURIComponent(variant) + '&paper=58mm',
                        {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        }
                    );
                    if (! res.ok) throw new Error('thermal fetch failed');
                    var thermal = await res.json();
                    var url = thermal.thermer_url || '';
                    if (! url && thermal.thermer_json) {
                        url = 'thermer://?data=' + encodeURIComponent(thermal.thermer_json);
                        if (url.length > 1800) url = '';
                    }
                    // Prioritas Intent (BAF + package) sesuai docs Thermer Android
                    if (thermal.intent_url) {
                        url = thermal.intent_url;
                    } else if (! url && thermal.thermer_baf_text) {
                        url = 'intent:#Intent;action=android.intent.action.SEND;type=text/plain;'
                            + 'package=mate.bluetoothprint;'
                            + 'S.android.intent.extra.TEXT=' + encodeURIComponent(thermal.thermer_baf_text)
                            + ';end';
                    }
                    if (! url) {
                        if (hint) hint.textContent = 'Data Thermer belum siap. Pastikan Thermer terpasang.';
                        return;
                    }
                    openThermer(url);
                    if (hint) {
                        hint.innerHTML = 'Thermer harus terbuka. Jika tidak, pastikan printer sudah dipilih di Thermer.';
                    }
                } catch (e) {
                    if (hint) hint.textContent = 'Gagal membuka Thermer. Kembali ke struk dan coba lagi.';
                }
            }

            setTimeout(function () {
                if (isAndroid) {
                    printViaThermer();
                } else {
                    window.print();
                }
            }, 400);
        });
    </script>
</body>
</html>
