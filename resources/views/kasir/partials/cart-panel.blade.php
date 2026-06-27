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
            <ul class="pos-receipt-lines">
                @foreach ($order->items as $item)
                    <li class="pos-receipt-line" data-kasir-item>
                        <div class="pos-receipt-line-main">
                            <p class="pos-receipt-line-name">{{ $item->product->name }}</p>
                            <p class="pos-receipt-line-qty">{{ $format::number($item->quantity, 0) }} × {{ $format::rupiah($item->unit_price) }}</p>
                        </div>
                        <div class="pos-receipt-line-side">
                            <span class="pos-receipt-line-total">{{ $format::rupiah($item->line_total) }}</span>
                            <form action="{{ route('kasir.items.destroy', $item) }}" method="POST">
                                @csrf @method('DELETE')
                                <button type="submit" class="pos-line-remove" aria-label="Hapus">×</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
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
