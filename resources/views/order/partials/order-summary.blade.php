@props(['editable' => false])

<div class="order-cart-card" data-order-cart>
    <div class="order-cart-head">
        <div>
            <h2 class="font-semibold text-slate-900">Pesanan Anda</h2>
            <p class="text-xs text-slate-500">{{ $order->order_number }}</p>
        </div>
        <span class="badge badge-slate">{{ $order->items->count() }} item</span>
    </div>

    <div class="order-cart-items">
        @forelse ($order->items as $item)
            <div class="order-cart-row-wrap">
                <x-pos-order-item
                    :item="$item"
                    :format="$format"
                    :editable="$editable"
                    :update-url="$editable ? route('order.menu.items.update', $item) : null"
                    :destroy-url="$editable ? route('order.menu.items.destroy', $item) : null"
                    line-class="order-cart-row"
                />
            </div>
        @empty
            <div class="order-cart-empty">
                <p>Belum ada item dipilih</p>
                <p class="order-cart-empty-hint">Pilih menu lalu tambahkan ke pesanan</p>
            </div>
        @endforelse
    </div>

    @if ($order->items->isNotEmpty())
        <div class="order-cart-total">
            <span>Total</span>
            <span class="text-brand-600">{{ $format::rupiah($order->total) }}</span>
        </div>
    @endif
</div>
