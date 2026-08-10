@php
    $shopName = (string) config('pos.shop_name', 'Coffee & Kitchen');
    $variant = $variant ?? 'customer';
    $isStation = in_array($variant, ['kitchen', 'bar'], true);
    $stationTitle = $variant === 'bar' ? 'Struk Bar' : 'Struk Dapur';
    $emptyStationLabel = $variant === 'bar' ? 'Tidak ada item minuman' : 'Tidak ada item dapur';
    $paidAt = $order->paid_at?->format('d/m/Y H:i')
        ?? $order->updated_at?->format('d/m/Y H:i')
        ?? now()->format('d/m/Y H:i');
    $isOpenBill = method_exists($order, 'isOpenBill') && $order->isOpenBill();
    $paper = strtolower(trim((string) ($paper ?? config('pos.thermal.paper', '58mm'))));
    $paper = $paper === '80mm' ? '80mm' : '58mm';
    $paperWidth = $paper === '80mm' ? '80mm' : '58mm';

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
<html lang="id" data-paper="{{ $paper }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $order->order_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background: #e8e8e8;
            padding: 16px;
            -webkit-font-smoothing: none;
            -moz-osx-font-smoothing: unset;
            font-smooth: never;
            text-rendering: geometricPrecision;
        }
        .sheet {
            width: var(--sheet-width, {{ $paperWidth }});
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 6px 4px 12px;
            font-size: 12px;
            line-height: 1.35;
            font-weight: 700;
            color: #000;
        }
        html[data-paper="80mm"] { --sheet-width: 80mm; }
        html[data-paper="58mm"] { --sheet-width: 58mm; }
        .center { text-align: center; }
        .shop {
            font-size: 16px;
            font-weight: 900;
            margin-bottom: 2px;
            letter-spacing: 0;
        }
        .eyebrow {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .order-no {
            font-size: 13px;
            font-weight: 900;
        }
        .meta {
            margin-top: 1px;
            font-size: 12px;
            font-weight: 700;
        }
        .sep {
            border: 0;
            border-top: 2px solid #000;
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
            font-size: 12px;
            font-weight: 700;
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
            font-weight: 900;
        }
        table.lines tr.total td {
            font-weight: 900;
            font-size: 13px;
            padding-top: 4px;
        }
        table.lines td.check {
            width: 14px;
            text-align: right;
            font-weight: 700;
        }
        .box {
            display: inline-block;
            width: 10px;
            height: 10px;
            border: 2px solid #000;
            vertical-align: middle;
            background: #fff;
        }
        .note {
            font-size: 11px;
            font-weight: 700;
            margin: 0;
            padding-left: 2px;
            color: #000;
        }
        .pay-meta {
            margin-top: 3px;
            font-size: 12px;
            font-weight: 700;
            text-align: left;
        }
        .footer {
            margin-top: 12px;
            text-align: center;
            font-weight: 900;
            font-size: 13px;
        }
        .footer.muted {
            font-weight: 700;
            font-size: 11px;
        }
        /* Struk dapur: font lebih besar agar mudah dibaca di dapur */
        .sheet.is-kitchen {
            font-size: 15px;
            line-height: 1.4;
            padding: 8px 5px 14px;
        }
        .sheet.is-kitchen .shop { font-size: 19px; }
        .sheet.is-kitchen .eyebrow { font-size: 14px; margin-bottom: 8px; }
        .sheet.is-kitchen .order-no { font-size: 16px; }
        .sheet.is-kitchen .meta { font-size: 14px; }
        .sheet.is-kitchen .sep { margin: 10px 0; }
        .sheet.is-kitchen table.lines td {
            font-size: 15px;
            padding: 4px 0;
            font-weight: 900;
        }
        .sheet.is-kitchen table.lines td.check { width: 18px; }
        .sheet.is-kitchen .box {
            width: 13px;
            height: 13px;
            border-width: 2px;
        }
        .sheet.is-kitchen .note {
            font-size: 14px;
            font-weight: 700;
            padding-left: 4px;
        }
        .sheet.is-kitchen .pay-meta { font-size: 14px; }
        .sheet.is-kitchen .footer.muted { font-size: 13px; }
        html[data-paper="80mm"] .sheet {
            font-size: 13px;
            padding: 8px 6px 14px;
        }
        html[data-paper="80mm"] .shop { font-size: 18px; }
        html[data-paper="80mm"] .order-no { font-size: 14px; }
        html[data-paper="80mm"] .sheet.is-kitchen { font-size: 16px; }
        html[data-paper="80mm"] .sheet.is-kitchen .shop { font-size: 20px; }
        html[data-paper="80mm"] .sheet.is-kitchen table.lines td { font-size: 16px; }
        .hint {
            max-width: 320px;
            margin: 12px auto 0;
            text-align: center;
            font-size: 12px;
            color: #555;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 400;
        }
        .hint button, .paper-picker button {
            margin-top: 8px;
            padding: 8px 14px;
            border: 0;
            border-radius: 8px;
            background: #5c4033;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
        }
        .paper-picker {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .paper-picker button {
            margin-top: 0;
            background: #fff;
            color: #5c4033;
            border: 1px solid #5c4033;
        }
        .paper-picker button.is-active {
            background: #5c4033;
            color: #fff;
        }
        .hint-note {
            margin-top: 8px;
            font-size: 11px;
            color: #777;
            line-height: 1.4;
        }
        @media print {
            @page {
                size: var(--sheet-width, {{ $paperWidth }}) auto;
                margin: 1mm;
            }
            html, body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
                width: var(--sheet-width, {{ $paperWidth }}) !important;
                height: auto !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
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
            @if ($isOpenBill)
                <div class="meta"><strong>TAGIHAN TERBUKA</strong></div>
            @endif
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
        Pilih lebar kertas sesuai printer (POS-58 / Rongta), lalu cetak.
        <div class="paper-picker" role="group" aria-label="Lebar kertas thermal">
            <button type="button" data-paper-choice="58mm">58mm (POS-58)</button>
            <button type="button" data-paper-choice="80mm">80mm (Rongta)</button>
        </div>
        <button type="button" id="print-again-btn">Cetak lagi</button>
        <p class="hint-note">
            Di dialog cetak Windows: pilih ukuran kertas printer (bukan A4), matikan Fit to page / Shrink to fit.
        </p>
    </div>

    <script>
        window.addEventListener('load', function () {
            var isAndroid = /Android/i.test(navigator.userAgent || '');
            var thermalJsonRoute = @json($thermalJsonRoute ?? null);
            var variant = @json($variant ?? 'customer');
            var serverPaper = @json($paper);
            var PAPER_KEY = 'pos-thermal-paper';
            var hint = document.getElementById('print-hint');
            var printBtn = document.getElementById('print-again-btn');
            var paperButtons = document.querySelectorAll('[data-paper-choice]');
            var autoPrinted = false;

            function normalizePaper(value) {
                value = String(value || '').toLowerCase().trim();
                return value === '80mm' || value === '80' ? '80mm' : '58mm';
            }

            function currentPaper() {
                return normalizePaper(document.documentElement.getAttribute('data-paper') || serverPaper);
            }

            function syncPaperButtons() {
                var paper = currentPaper();
                paperButtons.forEach(function (btn) {
                    btn.classList.toggle('is-active', btn.getAttribute('data-paper-choice') === paper);
                });
            }

            function applyPaper(paper, persist) {
                paper = normalizePaper(paper);
                document.documentElement.setAttribute('data-paper', paper);
                if (persist !== false) {
                    try { localStorage.setItem(PAPER_KEY, paper); } catch (e) {}
                }
                syncPaperButtons();
                return paper;
            }

            function resolveInitialPaper() {
                var params = new URLSearchParams(window.location.search || '');
                var fromQuery = params.get('paper');
                if (fromQuery === '58mm' || fromQuery === '80mm' || fromQuery === '58' || fromQuery === '80') {
                    return normalizePaper(fromQuery);
                }
                try {
                    var saved = localStorage.getItem(PAPER_KEY);
                    if (saved === '58mm' || saved === '80mm') {
                        return saved;
                    }
                } catch (e) {}
                return normalizePaper(serverPaper);
            }

            function openThermer(url) {
                var a = document.createElement('a');
                a.href = url;
                a.style.display = 'none';
                a.setAttribute('rel', 'noopener');
                document.body.appendChild(a);
                a.click();
                setTimeout(function () {
                    try { document.body.removeChild(a); } catch (e) {}
                }, 1000);
            }

            async function printViaThermer() {
                if (! thermalJsonRoute) {
                    if (hint) hint.innerHTML = 'Buka halaman struk, lalu klik Cetak di sana agar Thermer terbuka.';
                    return;
                }
                if (hint) {
                    var note = hint.querySelector('.hint-note');
                    var picker = hint.querySelector('.paper-picker');
                    hint.textContent = 'Membuka Thermer…';
                    if (picker) hint.appendChild(picker);
                    if (printBtn) hint.appendChild(printBtn);
                    if (note) hint.appendChild(note);
                }
                try {
                    var paper = currentPaper();
                    var res = await fetch(
                        thermalJsonRoute
                            + '?variant=' + encodeURIComponent(variant)
                            + '&paper=' + encodeURIComponent(paper),
                        {
                            credentials: 'same-origin',
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        }
                    );
                    if (! res.ok) throw new Error('thermal fetch failed');
                    var thermal = await res.json();
                    var url = thermal.thermer_browser_url || '';
                    if (! url && thermal.thermer_url) {
                        url = thermal.thermer_url;
                    }
                    if (! url && thermal.thermer_json) {
                        url = 'thermer://?data=' + encodeURIComponent(thermal.thermer_json);
                        if (url.length > 1800) url = '';
                    }
                    if (! url) {
                        if (hint) hint.textContent = 'Data Thermer belum siap. Aktifkan Browser Print di settings Thermer.';
                        return;
                    }
                    openThermer(url);
                    if (hint) {
                        hint.innerHTML = 'Thermer harus terbuka. Jika ke Play Store: buka Thermer → Settings → aktifkan Browser Print.';
                    }
                } catch (e) {
                    if (hint) hint.textContent = 'Gagal membuka Thermer. Kembali ke struk dan coba lagi.';
                }
            }

            applyPaper(resolveInitialPaper(), true);

            paperButtons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    applyPaper(btn.getAttribute('data-paper-choice'), true);
                });
            });

            if (printBtn) {
                printBtn.addEventListener('click', function () {
                    if (isAndroid) {
                        printViaThermer();
                    } else {
                        window.print();
                    }
                });
            }

            setTimeout(function () {
                if (autoPrinted) return;
                autoPrinted = true;
                if (isAndroid) {
                    printViaThermer();
                } else {
                    window.print();
                }
            }, 450);
        });
    </script>
</body>
</html>
