@props(['editable' => false, 'table' => null])

<div class="order-cart-card">
    <div class="order-cart-head">
        <h2 class="font-semibold text-slate-900">Pesanan Anda</h2>
        <span class="badge badge-slate">{{ $order->items->count() }} item</span>
    </div>

    <div class="order-cart-items">
        @foreach ($order->items as $item)
            <div class="order-cart-row">
                <div class="min-w-0 flex-1">
                    <p class="font-medium text-slate-900">{{ $item->product->name }}</p>
                    <p class="text-xs text-slate-500">{{ $format::number($item->quantity, 0) }} × {{ $format::rupiah($item->unit_price) }}</p>
                </div>
                <p class="shrink-0 font-semibold">{{ $format::rupiah($item->line_total) }}</p>
                @if ($editable && $table)
                    <form action="{{ route('order.table.items.destroy', [$table->barcode_token, $item]) }}" method="POST" class="shrink-0">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-outline-danger btn-sm min-h-10 min-w-10 px-0" aria-label="Hapus {{ $item->product->name }}">×</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    <div class="order-cart-total">
        <span>Total</span>
        <span class="text-brand-600">{{ $format::rupiah($order->total) }}</span>
    </div>
</div>
