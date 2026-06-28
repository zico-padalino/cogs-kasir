<div class="pos-receipt">
    <div class="pos-receipt-head">
        <div>
            <h2 class="pos-receipt-title">Pesanan</h2>
            <p class="pos-receipt-meta">{{ $order->items->count() }} item · {{ $order->order_number }}</p>
        </div>
        <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
    </div>

    @if ($order->table)
        <div class="pos-receipt-table">
            Meja: <strong>{{ $order->table->label }}</strong>
        </div>
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
                <span class="pos-receipt-empty-icon">🧾</span>
                <p>Belum ada item</p>
                <p class="pos-receipt-empty-hint">Pilih menu di sebelah kiri</p>
            </div>
        @endif
    </div>

    @if ($order->items->isNotEmpty())
        <div class="pos-receipt-foot">
            <div class="pos-receipt-subtotal">
                <span>Subtotal</span>
                <span>{{ $format::rupiah($order->subtotal) }}</span>
            </div>
            <div class="pos-receipt-grand">
                <span>Total Bayar</span>
                <span data-kasir-total>{{ $format::rupiah($order->total) }}</span>
            </div>

            <form action="{{ route('kasir.pay') }}" method="POST" class="pos-pay-form">
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
                                {{ $index === 0 ? 'checked' : '' }}
                                required
                            >
                            <span class="pos-pay-option-text">{{ $method->label() }}</span>
                        </label>
                    @endforeach
                </div>
                <button
                    type="submit"
                    class="pos-pay-submit"
                    onclick="return confirm('Proses pembayaran? Stok & COGS akan tercatat otomatis.')"
                >
                    Bayar {{ $format::rupiah($order->total) }}
                </button>
            </form>
        </div>
    @endif
</div>
