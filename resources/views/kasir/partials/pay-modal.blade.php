@if ($order->items->isNotEmpty() && $order->canCheckoutAtKasir())
    <div class="pos-pay-modal hidden" data-kasir-pay-modal aria-hidden="true">
        <div class="pos-add-modal-backdrop" data-kasir-close-pay></div>
        <div class="pos-pay-modal-panel" role="dialog" aria-modal="true" aria-labelledby="kasir-pay-modal-title">
            <div class="pos-pay-modal-head">
                <div class="min-w-0 flex-1">
                    <p class="pos-pay-modal-eyebrow">Langkah 3 · Pembayaran</p>
                    <h2 id="kasir-pay-modal-title" class="pos-pay-modal-title">Total Bayar</h2>
                    <p class="pos-pay-modal-total" data-kasir-pay-modal-total data-pos-order-total="{{ $order->total }}">{{ $format::rupiah($order->total) }}</p>
                    <p class="pos-pay-modal-meta">
                        {{ $order->items->count() }} item · {{ $order->order_number }}
                        @if ($order->hasDiscount())
                            · diskon {{ $format::rupiah($order->discount_amount) }}
                        @endif
                    </p>
                </div>
                <button type="button" class="pos-add-modal-close" data-kasir-close-pay aria-label="Tutup">×</button>
            </div>

            <form
                action="{{ route('kasir.pay') }}"
                method="POST"
                enctype="multipart/form-data"
                class="pos-pay-form"
                data-pos-pay-form
                data-pos-pay-form-modal
            >
                @csrf
                <p class="pos-pay-label">Metode pembayaran</p>
                <div class="pos-pay-grid">
                    @foreach (\App\Enums\PaymentMethod::cases() as $index => $method)
                        <label class="pos-pay-option {{ $index === 0 ? 'is-selected' : '' }}">
                            <input
                                type="radio"
                                name="payment_method"
                                value="{{ $method->value }}"
                                class="sr-only"
                                data-pos-payment-method
                                {{ $index === 0 ? 'checked' : '' }}
                                required
                            >
                            <span class="pos-pay-option-text">{{ $method->label() }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="pos-cash-panel hidden" data-pos-cash-panel>
                    <label class="pos-pay-label" for="pos-amount-received-modal">Uang diterima</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500">Rp</span>
                        <input
                            id="pos-amount-received-modal"
                            type="text"
                            inputmode="numeric"
                            enterkeyhint="done"
                            class="pos-cash-input pl-10"
                            placeholder="0"
                            value=""
                            data-pos-amount-received
                            autocomplete="off"
                        >
                        <input type="hidden" name="amount_received" value="" data-pos-amount-received-value>
                    </div>
                    <p class="pos-cash-change" data-pos-change-wrap>
                        Kembalian: <strong data-pos-change-amount>Rp 0</strong>
                    </p>
                </div>

                <div class="pos-qris-panel hidden" data-pos-qris-panel>
                    <p class="pos-pay-label">Scan QRIS</p>
                    <div class="pos-qris-frame">
                        <img
                            src="{{ asset('qris.jpeg') }}"
                            alt="Kode QRIS Kedai Tjoan"
                            class="pos-qris-image"
                            data-pos-qris-image
                        >
                    </div>
                    <p class="pos-qris-hint">Minta pelanggan scan kode di atas, lalu unggah bukti pembayaran.</p>
                </div>

                <div class="pos-proof-panel hidden" data-pos-proof-panel>
                    <label class="pos-pay-label" for="pos-payment-proof">Foto bukti pembayaran</label>
                    <label class="pos-proof-drop" for="pos-payment-proof">
                        <input
                            id="pos-payment-proof"
                            type="file"
                            name="payment_proof"
                            accept="image/*"
                            capture="environment"
                            class="sr-only"
                            data-pos-payment-proof
                        >
                        <span class="pos-proof-drop-icon" aria-hidden="true">📷</span>
                        <span class="pos-proof-drop-title" data-pos-proof-title>Ambil / unggah foto</span>
                        <span class="pos-proof-drop-hint">JPG, PNG, WEBP · maks. 5 MB</span>
                    </label>
                    <div class="pos-proof-preview hidden" data-pos-proof-preview>
                        <img src="" alt="Pratinjau bukti bayar" class="pos-proof-preview-image" data-pos-proof-preview-image>
                        <button type="button" class="pos-proof-clear" data-pos-proof-clear>Ganti foto</button>
                    </div>
                    <p class="pos-proof-error hidden" data-pos-proof-error>Bukti pembayaran wajib untuk QRIS / Transfer.</p>
                </div>

                <button
                    type="submit"
                    class="pos-pay-submit"
                    data-pos-pay-submit
                >
                    Bayar &amp; Selesaikan {{ $format::rupiah($order->total) }}
                </button>
            </form>
        </div>
    </div>
@endif
