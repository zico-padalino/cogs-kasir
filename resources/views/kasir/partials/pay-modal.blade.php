@if ($order->items->isNotEmpty() && $order->canCheckoutAtKasir())
    <div class="pos-pay-modal hidden" data-kasir-pay-modal aria-hidden="true">
        <div class="pos-add-modal-backdrop" data-kasir-close-pay></div>
        <div class="pos-pay-modal-panel" role="dialog" aria-modal="true" aria-labelledby="kasir-pay-modal-title">
            <div class="pos-pay-modal-head">
                <div class="min-w-0 flex-1">
                    <p class="pos-pay-modal-eyebrow">Pembayaran</p>
                    <h2 id="kasir-pay-modal-title" class="pos-pay-modal-title">Total Bayar</h2>
                    <p class="pos-pay-modal-total" data-kasir-pay-modal-total data-pos-order-total="{{ $order->total }}">{{ $format::rupiah($order->total) }}</p>
                    <p class="pos-pay-modal-meta" data-kasir-pay-modal-meta>
                        {{ $order->items->count() }} item · {{ $order->order_number }}
                        <span
                            class="pos-pay-modal-discount {{ $order->hasDiscount() ? '' : 'hidden' }}"
                            data-kasir-pay-modal-discount
                        >
                            · diskon <span data-kasir-pay-modal-discount-amount>{{ $format::rupiah($order->discount_amount) }}</span>
                        </span>
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

                <div
                    class="pos-qris-panel hidden"
                    data-pos-qris-panel
                    data-qris-refresh-url="{{ route('kasir.orders.qris', $order) }}"
                    data-qris-order-id="{{ $order->id }}"
                >
                    <ol class="pos-qris-steps" aria-label="Langkah bayar QRIS">
                        <li><span>1</span> Pelanggan scan QR</li>
                        <li><span>2</span> Foto bukti (opsional)</li>
                        <li><span>3</span> Konfirmasi bayar</li>
                    </ol>
                    <p class="pos-pay-label">1 · Kode QRIS</p>
                    @php
                        $qrisPay = app(\App\Services\QrisDynamicService::class)->forAmount($order->total);
                    @endphp
                    <x-qris-dynamic :qris="$qrisPay" />
                </div>

                <div class="pos-proof-panel hidden" data-pos-proof-panel>
                    <p class="pos-pay-label">Foto bukti pembayaran (opsional)</p>
                    <div data-pos-proof-pick-row>
                        <div class="order-proof-pick">
                            <label class="order-proof-pick-btn">
                                <input
                                    type="file"
                                    accept="image/*"
                                    data-pos-payment-proof-pick="gallery"
                                >
                                <span>Dari galeri</span>
                            </label>
                            <label class="order-proof-pick-btn">
                                <input
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    data-pos-payment-proof-pick="camera"
                                >
                                <span>Ambil foto</span>
                            </label>
                        </div>
                        <p class="pos-proof-drop-hint">Opsional · JPG, PNG, WEBP, HEIC · maks. 5 MB</p>
                    </div>
                    <input
                        id="pos-payment-proof"
                        type="file"
                        name="payment_proof"
                        accept="image/*"
                        class="sr-only"
                        data-pos-payment-proof
                        tabindex="-1"
                    >
                    <div class="pos-proof-preview hidden" data-pos-proof-preview>
                        <img src="" alt="Pratinjau bukti bayar" class="pos-proof-preview-image" data-pos-proof-preview-image>
                        <button type="button" class="pos-proof-clear" data-pos-proof-clear>Ganti foto</button>
                    </div>
                    <p class="pos-proof-error hidden" data-pos-proof-error></p>
                </div>

                <button
                    type="submit"
                    class="pos-pay-submit"
                    data-pos-pay-submit
                >
                    Bayar <span data-pos-pay-submit-total>{{ $format::rupiah($order->total) }}</span>
                </button>
            </form>
        </div>
    </div>
@endif
