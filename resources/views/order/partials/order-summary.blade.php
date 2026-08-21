@props([
    'editable' => false,
    'title' => 'Pesanan Anda',
    'subtitle' => null,
    'showMeta' => false,
])

<div class="order-cart-card" data-order-cart>
    <div class="order-cart-head">
        <div class="min-w-0">
            <h2 class="font-semibold text-slate-900">{{ $title }}</h2>
            <p class="text-xs text-slate-500">
                {{ $subtitle ?: $order->order_number }}
            </p>
        </div>
        <span class="badge badge-slate">{{ $order->items->count() }} item</span>
    </div>

    @if ($showMeta)
        <div class="order-cart-meta">
            <div class="order-cart-meta-row">
                <span>Nomor</span>
                <span class="font-mono font-semibold">{{ $order->order_number }}</span>
            </div>
            @if ($order->order_type)
                <div class="order-cart-meta-row">
                    <span>Tipe</span>
                    <span>{{ $order->order_type->icon() }} {{ $order->order_type->label() }}</span>
                </div>
            @endif
            @if ($order->customer_note)
                <div class="order-cart-meta-row">
                    <span>Nama</span>
                    <span class="truncate font-medium">{{ $order->customer_note }}</span>
                </div>
            @endif
        </div>
    @endif

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
