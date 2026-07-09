 @extends('layouts.kasir')

@section('title', 'Struk')
@section('heading', 'Struk Pembayaran')

@section('content')
    @php
        $shopName = config('pos.shop_name', 'Coffee & Kitchen');
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
                            @if ($item->notes)
                                <p class="mt-1 text-xs text-amber-700">Catatan: {{ $item->notes }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="text-2xl font-bold text-brand-600">{{ $format::rupiah($order->total) }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $order->payment_method?->label() }}</p>

            @if ($order->payment_method?->value === 'cash' && $order->amount_received)
                <div class="mt-3 space-y-1 text-sm text-slate-600">
                    <p>Uang diterima: {{ $format::rupiah($order->amount_received) }}</p>
                    <p>Kembalian: <strong>{{ $format::rupiah($order->change_amount) }}</strong></p>
                </div>
            @endif

            <p class="mt-4 text-xs text-slate-400">Stok & biaya pokok tercatat otomatis</p>
        </div>

        <div class="form-actions mt-4 no-print">
            <a
                href="{{ $pdfRoute }}?print=1"
                target="_blank"
                rel="noopener"
                class="btn-primary w-full"
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

    @php
        $receiptWaPayload = [
            'message' => $waMessage,
        ];
    @endphp

    <script type="application/json" id="receipt-wa-payload">{!! json_encode($receiptWaPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

    <script>
        (function () {
            var openBtn = document.querySelector('[data-receipt-wa-open]');
            var panel = document.querySelector('[data-receipt-wa-panel]');
            var phoneInput = document.querySelector('[data-receipt-wa-phone]');
            var sendBtn = document.querySelector('[data-receipt-wa-send]');
            var cancelBtn = document.querySelector('[data-receipt-wa-cancel]');
            var errorEl = document.querySelector('[data-receipt-wa-error]');
            var payloadEl = document.getElementById('receipt-wa-payload');

            if (!payloadEl || !openBtn || !panel || !phoneInput || !sendBtn) {
                return;
            }

            var payload = { message: '' };
            try {
                Object.assign(payload, JSON.parse(payloadEl.textContent || '{}'));
            } catch (e) {
                // ignore parse error
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
        })();
    </script>
@endsection

