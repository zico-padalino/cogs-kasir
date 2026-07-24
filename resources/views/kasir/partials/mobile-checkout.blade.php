@props(['order', 'format'])

@if ($order->items->isNotEmpty())
    <div class="pos-mobile-checkout lg:hidden" data-pos-mobile-checkout>
        <div class="pos-mobile-checkout-info">
            <span class="pos-mobile-checkout-label">{{ $order->items->count() }} item di keranjang</span>
            <span class="pos-mobile-checkout-total" data-kasir-mobile-total>{{ $format::rupiah($order->total) }}</span>
        </div>
        <button type="button" class="pos-mobile-checkout-btn" data-kasir-go-cart>
            <span data-kasir-go-cart-label>Lihat pesanan</span>
        </button>
    </div>
@endif
