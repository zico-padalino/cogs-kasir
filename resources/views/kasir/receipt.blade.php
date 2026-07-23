@extends('layouts.kasir')

@section('title', 'Struk')
@section('heading', 'Struk Pembayaran')

@section('content')
    @php
        $shopName = config('pos.shop_name', 'Coffee & Kitchen');
        $thermal = $thermal ?? [];
    @endphp

    <div class="mx-auto max-w-md px-1">
        <div class="card border-2 border-dashed border-slate-300 bg-white text-center" id="receipt">
            <p class="text-xs uppercase tracking-widest text-slate-500">Struk Pembayaran</p>
            <h1 class="mt-2 text-xl font-bold">{{ $shopName }}</h1>
            <p class="mt-1 font-mono text-sm">{{ $order->order_number }}</p>
            <p class="text-xs text-slate-500">{{ $order->paid_at?->format('d/m/Y H:i') }}</p>

            @if ($order->order_type)
                <p class="mt-2 text-sm">{{ $order->order_type->icon() }} {{ $order->order_type->label() }}</p>
            @endif

            @if ($order->table)
                <p class="text-sm">Meja: <strong>{{ $order->table->label }}</strong></p>
            @endif

            @if ($order->customer_note)
                <p class="text-sm text-slate-600">Pelanggan: {{ $order->customer_note }}</p>
            @endif

            <div class="my-6 border-t border-b border-slate-200 py-4 text-left text-sm">
                @foreach ($order->items as $item)
                    <div class="mb-3 flex gap-3">
                        <x-product-image :product="$item->product" class="h-12 w-12 shrink-0 rounded-lg" />
                        <div class="min-w-0 flex-1">
                            <div class="flex justify-between gap-2">
                                <span>{{ $item->product->name }} × {{ $format::number($item->quantity, 0) }}</span>
                                <span class="shrink-0 font-medium">{{ $format::rupiah($item->line_total) }}</span>
                            </div>
                            @php
                                $noteParts = \App\Support\PosItemNotes::split($item->notes);
                            @endphp
                            @if ($noteParts['addon_labels'] !== [])
                                <p class="mt-1 text-xs text-brand-700">{{ implode(' · ', $noteParts['addon_labels']) }}</p>
                            @endif
                            @if ($noteParts['customer'])
                                <p class="mt-0.5 text-xs text-amber-700">Catatan: {{ $noteParts['customer'] }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($order->hasDiscount())
                <div class="my-4 space-y-1 border-t border-slate-200 pt-4 text-left text-sm text-slate-600">
                    <div class="flex justify-between gap-3">
                        <span>Subtotal</span>
                        <span>{{ $format::rupiah($order->subtotal) }}</span>
                    </div>
                    <div class="flex justify-between gap-3 text-rose-600">
                        <span>Diskon</span>
                        <span>- {{ $format::rupiah($order->discount_amount) }}</span>
                    </div>
                </div>
            @endif

            <p class="text-2xl font-bold text-brand-600">{{ $format::rupiah($order->total) }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $order->payment_method?->label() }}</p>

            @if ($order->payment_method?->value === 'cash' && $order->amount_received)
                <div class="mt-3 space-y-1 text-sm text-slate-600">
                    <p>Uang diterima: {{ $format::rupiah($order->amount_received) }}</p>
                    <p>Kembalian: <strong>{{ $format::rupiah($order->change_amount) }}</strong></p>
                </div>
            @endif

            @if ($order->cashierDisplayName() !== '-')
                <p class="mt-2 text-sm text-slate-600">Kasir: <strong>{{ $order->cashierDisplayName() }}</strong></p>
            @endif

            <p class="mt-4 text-xs text-slate-400">Biaya pokok tercatat otomatis</p>
        </div>

        <div class="form-actions mt-4 no-print">
            <button type="button" class="btn-primary w-full" data-receipt-thermal-print>
                Cetak Thermal (Ainuo)
            </button>
            <div class="grid grid-cols-2 gap-2">
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                    <input type="radio" name="thermal-paper" value="58mm" data-thermal-paper checked class="accent-brand-600">
                    58mm
                </label>
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                    <input type="radio" name="thermal-paper" value="80mm" data-thermal-paper class="accent-brand-600">
                    80mm
                </label>
            </div>
            <p class="text-xs text-slate-500" data-thermal-hint>
                Android: pasang RawBT, pair printer Ainuo di Bluetooth, lalu cetak.
            </p>
            <a
                href="{{ $pdfRoute }}?print=1"
                target="_blank"
                rel="noopener"
                class="btn-secondary w-full"
                data-receipt-print
            >Cetak PDF</a>
            <button type="button" class="btn-secondary w-full" data-receipt-wa-open>
                Kirim WhatsApp
            </button>
            <a href="{{ route('kasir.index') }}" class="btn-outline w-full">POS Baru</a>
        </div>

        <div class="receipt-wa-panel hidden no-print" data-receipt-wa-panel>
            <label class="form-label" for="receipt-wa-phone">Nomor WhatsApp pelanggan</label>
            <input
                id="receipt-wa-phone"
                type="tel"
                inputmode="tel"
                class="form-input"
                placeholder="08xxxxxxxxxx"
                autocomplete="tel"
                data-receipt-wa-phone
            >
            <p class="mt-1.5 text-xs text-slate-500">
                Chat WhatsApp langsung dibuka ke nomor ini dengan tautan PDF struk.
            </p>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <button type="button" class="btn-primary w-full" data-receipt-wa-send>Kirim Sekarang</button>
                <button type="button" class="btn-outline w-full" data-receipt-wa-cancel>Batal</button>
            </div>
            <p class="mt-2 hidden text-sm text-red-600" data-receipt-wa-error></p>
        </div>
    </div>

    {{-- Preview thermal monospace for desktop window.print fallback --}}
    <div id="thermal-print-sheet" class="hidden" aria-hidden="true">
        <pre id="thermal-print-pre" style="font-family: ui-monospace, monospace; font-size: 12px; white-space: pre-wrap; margin: 0;"></pre>
    </div>

    @php
        $receiptPayload = [
            'message' => $waMessage,
            'thermal' => $thermal,
            'thermalRoute' => $thermalRoute ?? null,
            'orderNumber' => $order->order_number,
            'shopName' => $shopName,
            'items' => $order->items->map(function ($item) use ($format) {
                return [
                    'name' => $item->product->name ?? 'Item',
                    'qty' => $format::number($item->quantity, 0),
                    'total' => $format::rupiah($item->line_total),
                    'notes' => $item->notes,
                ];
            })->values(),
            'subtotal' => $format::rupiah($order->subtotal),
            'discount' => $order->hasDiscount() ? $format::rupiah($order->discount_amount) : null,
            'total' => $format::rupiah($order->total),
            'payment' => $order->payment_method?->label(),
            'cashier' => $order->cashierDisplayName(),
            'paidAt' => $order->paid_at?->format('d/m/Y H:i'),
            'table' => $order->table?->label,
            'customer' => $order->customer_note,
            'orderType' => $order->order_type?->label(),
            'received' => $order->payment_method?->value === 'cash' && $order->amount_received
                ? $format::rupiah($order->amount_received)
                : null,
            'change' => $order->payment_method?->value === 'cash' && $order->amount_received
                ? $format::rupiah($order->change_amount)
                : null,
        ];
    @endphp

    <script type="application/json" id="receipt-wa-payload">{!! json_encode($receiptPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

    <style>
        @media print {
            body * { visibility: hidden !important; }
            #thermal-print-sheet, #thermal-print-sheet * { visibility: visible !important; }
            #thermal-print-sheet {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 58mm;
            }
            .no-print { display: none !important; }
        }
    </style>

    <script>
        (function () {
            var openBtn = document.querySelector('[data-receipt-wa-open]');
            var panel = document.querySelector('[data-receipt-wa-panel]');
            var phoneInput = document.querySelector('[data-receipt-wa-phone]');
            var sendBtn = document.querySelector('[data-receipt-wa-send]');
            var cancelBtn = document.querySelector('[data-receipt-wa-cancel]');
            var errorEl = document.querySelector('[data-receipt-wa-error]');
            var payloadEl = document.getElementById('receipt-wa-payload');
            var thermalBtn = document.querySelector('[data-receipt-thermal-print]');
            var hintEl = document.querySelector('[data-thermal-hint]');
            var paperRadios = document.querySelectorAll('[data-thermal-paper]');

            if (!payloadEl) {
                return;
            }

            var payload = { message: '', thermal: {} };
            try {
                Object.assign(payload, JSON.parse(payloadEl.textContent || '{}'));
            } catch (e) {
                // ignore parse error
            }

            var PAPER_KEY = 'pos-thermal-paper';
            try {
                var savedPaper = localStorage.getItem(PAPER_KEY);
                if (savedPaper === '58mm' || savedPaper === '80mm') {
                    paperRadios.forEach(function (r) {
                        r.checked = r.value === savedPaper;
                    });
                }
            } catch (e) {}

            function selectedPaper() {
                var checked = document.querySelector('[data-thermal-paper]:checked');
                return checked ? checked.value : '58mm';
            }

            paperRadios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    try {
                        localStorage.setItem(PAPER_KEY, selectedPaper());
                    } catch (e) {}
                });
            });

            function isAndroid() {
                return /Android/i.test(navigator.userAgent || '');
            }

            function showError(text) {
                if (!errorEl) {
                    return;
                }
                errorEl.textContent = text;
                errorEl.classList.toggle('hidden', !text);
            }

            function normalizePhone(raw) {
                var digits = String(raw || '').replace(/\D+/g, '');
                if (digits.indexOf('0') === 0) {
                    digits = '62' + digits.slice(1);
                } else if (digits.indexOf('8') === 0 && digits.length >= 9) {
                    digits = '62' + digits;
                }
                return digits;
            }

            function openWhatsApp(phone, message) {
                var url = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(message);
                window.open(url, '_blank', 'noopener');
            }

            function padColumns(left, right, width) {
                left = String(left || '');
                right = String(right || '');
                var maxLeft = Math.max(1, width - right.length - 1);
                if (left.length > maxLeft) {
                    left = left.slice(0, Math.max(1, maxLeft - 1)) + '.';
                }
                var pad = Math.max(1, width - left.length - right.length);
                return left + ' '.repeat(pad) + right;
            }

            function buildPreviewText(width) {
                var lines = [];
                var sep = '-'.repeat(width);
                lines.push(payload.shopName || '');
                lines.push('Struk Pembayaran');
                lines.push(payload.orderNumber || '');
                lines.push(payload.paidAt || '');
                if (payload.orderType) lines.push(payload.orderType);
                if (payload.table) lines.push('Meja: ' + payload.table);
                if (payload.customer) lines.push('Pelanggan: ' + payload.customer);
                lines.push(sep);
                (payload.items || []).forEach(function (item) {
                    lines.push(padColumns(item.name + ' x ' + item.qty, item.total, width));
                    if (item.notes) lines.push('  ' + item.notes);
                });
                lines.push(sep);
                if (payload.discount) {
                    lines.push(padColumns('Subtotal', payload.subtotal, width));
                    lines.push(padColumns('Diskon', '- ' + payload.discount, width));
                }
                lines.push(padColumns('TOTAL', payload.total, width));
                if (payload.payment) lines.push('Bayar: ' + payload.payment);
                if (payload.received) lines.push('Diterima: ' + payload.received);
                if (payload.change) lines.push('Kembalian: ' + payload.change);
                if (payload.cashier && payload.cashier !== '-') lines.push('Kasir: ' + payload.cashier);
                lines.push('');
                lines.push('Terima kasih');
                return lines.join('\n');
            }

            function printDesktopFallback() {
                var width = selectedPaper() === '80mm' ? 48 : 32;
                var pre = document.getElementById('thermal-print-pre');
                var sheet = document.getElementById('thermal-print-sheet');
                if (!pre || !sheet) {
                    window.print();
                    return;
                }
                pre.textContent = buildPreviewText(width);
                sheet.style.width = selectedPaper() === '80mm' ? '80mm' : '58mm';
                sheet.classList.remove('hidden');
                window.print();
                setTimeout(function () {
                    sheet.classList.add('hidden');
                }, 500);
            }

            async function fetchThermal(paper) {
                var base = payload.thermalRoute || '';
                if (!base) {
                    return payload.thermal || {};
                }
                var url = base + (base.indexOf('?') >= 0 ? '&' : '?') + 'paper=' + encodeURIComponent(paper) + '&format=json';
                // Prefer server JSON via same receipt page data; re-fetch binary endpoint as JSON not available —
                // use intent from initial payload when paper matches, else hit API-less rebuild via query on thermal route:
                // Web thermal route returns binary. Rebuild intent client-side is hard.
                // Instead: navigate with paper query to a JSON endpoint — use data attribute reload.
                try {
                    var res = await fetch(base + '?paper=' + encodeURIComponent(paper), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    // binary response — fall back to embedded payload when paper matches default
                } catch (e) {}
                return payload.thermal || {};
            }

            async function printThermal() {
                var paper = selectedPaper();
                try {
                    localStorage.setItem(PAPER_KEY, paper);
                } catch (e) {}

                var thermal = payload.thermal || {};
                // Reload thermal payload for selected paper from thermal route with Accept json via dedicated meta
                // Use hidden endpoint: append format=json on thermalRoute by fetching receipt page? 
                // Simpler: fetch /receipt/{id}/thermal?paper=xx as blob then base64 — heavy.
                // Use server-provided URLs with paper query by reconstructing from base64 endpoint.
                var intentUrl = thermal.intent_url;
                var rawbtUrl = thermal.rawbt_url;
                var playStore = thermal.rawbt_play_store || 'https://play.google.com/store/apps/details?id=ru.a402d.rawbtprinter';

                if (paper !== thermal.paper && payload.thermalRoute) {
                    try {
                        var jsonUrl = payload.thermalRoute.replace(/\/thermal$/, '/thermal-json');
                        // If thermal-json missing, request with header won't work. Add query paper to reload page data:
                        // Fetch binary and convert to base64 in browser.
                        var binRes = await fetch(payload.thermalRoute + '?paper=' + encodeURIComponent(paper), {
                            credentials: 'same-origin'
                        });
                        if (binRes.ok) {
                            var buf = await binRes.arrayBuffer();
                            var bytes = new Uint8Array(buf);
                            var binary = '';
                            for (var i = 0; i < bytes.length; i++) {
                                binary += String.fromCharCode(bytes[i]);
                            }
                            var b64 = btoa(binary);
                            intentUrl = 'intent:base64,' + b64 + '#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;end;';
                            rawbtUrl = 'rawbt:base64,' + b64;
                        }
                    } catch (err) {
                        // keep default thermal
                    }
                }

                if (isAndroid()) {
                    if (hintEl) {
                        hintEl.textContent = 'Membuka RawBT… Pastikan printer Ainuo sudah di-pair.';
                    }
                    var target = intentUrl || rawbtUrl;
                    if (!target) {
                        if (hintEl) hintEl.textContent = 'Data thermal belum siap.';
                        return;
                    }
                    window.location.href = target;
                    setTimeout(function () {
                        if (hintEl) {
                            hintEl.innerHTML = 'Jika tidak terbuka, <a class="underline text-brand-700" href="' + playStore + '" target="_blank" rel="noopener">pasang RawBT</a> lalu pair Ainuo.';
                        }
                    }, 1800);
                    return;
                }

                // Desktop / iOS: print monospace sheet; user picks Ainuo/RawBT if installed as system printer
                if (hintEl) {
                    hintEl.textContent = 'Desktop: pilih printer Ainuo / RawBT di dialog cetak. Di Android Chrome gunakan RawBT.';
                }
                printDesktopFallback();
            }

            if (thermalBtn) {
                thermalBtn.addEventListener('click', function () {
                    printThermal();
                });
            }

            if (openBtn && panel && phoneInput && sendBtn) {
                openBtn.addEventListener('click', function () {
                    panel.classList.remove('hidden');
                    showError('');
                    phoneInput.focus();
                });

                if (cancelBtn) {
                    cancelBtn.addEventListener('click', function () {
                        panel.classList.add('hidden');
                        showError('');
                    });
                }

                sendBtn.addEventListener('click', function () {
                    var phone = normalizePhone(phoneInput.value);
                    if (!/^62\d{8,15}$/.test(phone)) {
                        showError('Nomor WhatsApp tidak valid. Pakai format 08xxxxxxxxxx.');
                        phoneInput.focus();
                        return;
                    }

                    if (!payload.message) {
                        showError('Pesan WhatsApp belum siap.');
                        return;
                    }

                    showError('');
                    openWhatsApp(phone, payload.message);
                });

                phoneInput.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        sendBtn.click();
                    }
                });
            }
        })();
    </script>
@endsection
