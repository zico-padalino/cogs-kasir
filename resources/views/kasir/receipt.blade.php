@extends('layouts.kasir')

@section('title', 'Struk')
@section('heading', 'Struk Pembayaran')
@section('body_class', 'is-receipt-page')
@section('main_class', 'px-4 py-4 sm:px-6 sm:py-6 receipt-scroll')

@section('content')
    @php
        $shopName = config('pos.shop_name', 'Coffee & Kitchen');
        $thermal = $thermal ?? [];
    @endphp

    <div class="receipt-page mx-auto max-w-md px-1">
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

        <div class="form-actions receipt-actions mt-4 no-print">
            {{-- Sama pola Cetak PDF: link target=_blank + ?print=1 --}}
            <a
                href="{{ route('kasir.receipt.thermal-print', $order) }}?paper=58mm&print=1"
                target="_blank"
                rel="noopener"
                class="btn-primary w-full text-center"
                data-receipt-thermal-print
            >Cetak Thermal</a>
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
                Sama seperti Cetak PDF: klik sekali, tab baru membuka Thermer.
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
            'thermalJsonRoute' => $thermalJsonRoute ?? null,
            'thermalPrintRoute' => $thermalPrintRoute ?? null,
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
            var paperRadios = document.querySelectorAll('[data-thermal-paper]');

            if (!payloadEl) {
                return;
            }

            var payload = { message: '' };
            try {
                Object.assign(payload, JSON.parse(payloadEl.textContent || '{}'));
            } catch (e) {
                // ignore parse error
            }

            var PAPER_KEY = 'pos-thermal-paper';
            var thermalPrintBase = payload.thermalPrintRoute || (thermalBtn && thermalBtn.getAttribute('href')) || '';

            function selectedPaper() {
                var checked = document.querySelector('[data-thermal-paper]:checked');
                return checked ? checked.value : '58mm';
            }

            function syncThermalLink() {
                if (!thermalBtn || !thermalPrintBase) {
                    return;
                }
                var paper = selectedPaper();
                var base = String(thermalPrintBase).split('?')[0];
                thermalBtn.setAttribute('href', base + '?paper=' + encodeURIComponent(paper) + '&print=1');
            }

            try {
                var savedPaper = localStorage.getItem(PAPER_KEY);
                if (savedPaper === '58mm' || savedPaper === '80mm') {
                    paperRadios.forEach(function (r) {
                        r.checked = r.value === savedPaper;
                    });
                }
            } catch (e) {}

            syncThermalLink();

            paperRadios.forEach(function (radio) {
                radio.addEventListener('change', function () {
                    try {
                        localStorage.setItem(PAPER_KEY, selectedPaper());
                    } catch (e) {}
                    syncThermalLink();
                });
            });

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
