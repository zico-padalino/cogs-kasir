<div class="pos-receipt">
    <div class="pos-receipt-head">
        <div>
            <h2 class="pos-receipt-title">Pesanan</h2>
            <p class="pos-receipt-meta">{{ $order->items->count() }} item · {{ $order->order_number }}</p>
        </div>
        <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
    </div>

    @if ($order->order_type || $order->customer_note)
        <div class="pos-receipt-context" data-pos-receipt-context>
            @if ($order->order_type)
                <span class="pos-context-badge pos-context-badge-type" data-pos-receipt-type>{{ $order->order_type->icon() }} {{ $order->order_type->label() }}</span>
            @endif
            @if ($order->customer_note)
                <span class="pos-context-badge pos-context-badge-customer" data-pos-receipt-customer>{{ $order->customer_note }}</span>
            @endif
        </div>
    @else
        <div class="pos-receipt-context hidden" data-pos-receipt-context></div>
    @endif

    <div class="pos-receipt-body">
        @if ($order->items->isNotEmpty())
            <div class="pos-receipt-lines">
                @foreach ($order->items as $item)
                    <x-pos-order-item
                        :item="$item"
                        :format="$format"
                        :editable="$order->isKasirEditable()"
                        :update-url="route('kasir.items.update', $item)"
                        :destroy-url="route('kasir.items.destroy', $item)"
                    />
                @endforeach
            </div>
        @else
            <div class="pos-receipt-empty">
                <span class="pos-receipt-empty-icon">☕</span>
                <p>Belum ada item</p>
                <p class="pos-receipt-empty-hint">Tap menu di kiri untuk mulai pesanan</p>
            </div>
        @endif
    </div>

    @if ($order->items->isNotEmpty() && $order->needsKasirConfirmation())
        <div class="pos-receipt-confirm" data-pos-receipt-confirm>
            <div class="pos-receipt-pay-totals">
                <div class="pos-receipt-subtotal">
                    <span>Subtotal</span>
                    <span>{{ $format::rupiah($order->subtotal) }}</span>
                </div>
                <div class="pos-receipt-grand">
                    <span>Total Tagihan</span>
                    <span>{{ $format::rupiah($order->total) }}</span>
                </div>
            </div>

            <div class="pos-confirm-notice">
                <p class="pos-confirm-notice-title">Pesanan online menunggu konfirmasi</p>
                <p class="pos-confirm-notice-text">Pastikan pesanan sudah siap, lalu konfirmasi ke pelanggan sebelum pembayaran.</p>
            </div>

            <form action="{{ route('kasir.orders.confirm', $order) }}" method="POST" class="pos-confirm-form">
                @csrf
                <button
                    type="submit"
                    class="pos-confirm-submit"
                    onclick="return confirm('Konfirmasi pesanan {{ $order->customer_note ?: $order->order_number }} sudah selesai?')"
                >
                    Konfirmasi Pesanan Selesai
                </button>
            </form>
        </div>
    @elseif ($order->items->isNotEmpty() && $order->canCheckoutAtKasir())
        <div class="pos-receipt-pay" data-pos-receipt-pay>
            <div class="pos-receipt-pay-totals">
                <div class="pos-receipt-subtotal">
                    <span>Subtotal</span>
                    <span>{{ $format::rupiah($order->subtotal) }}</span>
                </div>
                <div class="pos-receipt-grand">
                    <span>Total Bayar</span>
                    <span data-kasir-total data-pos-order-total="{{ $order->total }}">{{ $format::rupiah($order->total) }}</span>
                </div>
            </div>

            <form action="{{ route('kasir.pay') }}" method="POST" class="pos-pay-form" data-pos-pay-form>
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
                    <label class="pos-pay-label" for="pos-amount-received">Uang diterima</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-500">Rp</span>
                        <input
                            id="pos-amount-received"
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

                <button
                    type="submit"
                    class="pos-pay-submit"
                    data-pos-pay-submit
                    onclick="return confirm('Proses pembayaran? Stok & COGS akan tercatat otomatis.')"
                >
                    Bayar {{ $format::rupiah($order->total) }}
                </button>
            </form>
        </div>
    @elseif ($order->items->isNotEmpty())
        <div class="pos-receipt-foot">
            <div class="pos-receipt-grand">
                <span>Total</span>
                <span>{{ $format::rupiah($order->total) }}</span>
            </div>
        </div>
    @endif
</div>

<x-product-image-lightbox />
