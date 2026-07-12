@props([
    'order',
    'format',
    'totalLabel' => 'Total Bayar',
])

<div
    class="pos-order-totals"
    data-pos-order-totals
    data-pos-subtotal="{{ $order->subtotal }}"
    data-pos-discount="{{ $order->discount_amount }}"
    data-pos-total="{{ $order->total }}"
>
    <div class="pos-receipt-subtotal">
        <span>Subtotal</span>
        <span data-pos-subtotal-label>{{ $format::rupiah($order->subtotal) }}</span>
    </div>
    <div class="pos-receipt-discount {{ $order->hasDiscount() ? '' : 'hidden' }}" data-pos-discount-row>
        <span>Diskon</span>
        <span data-pos-discount-label>- {{ $format::rupiah($order->discount_amount) }}</span>
    </div>
    <div class="pos-receipt-grand">
        <span>{{ $totalLabel }}</span>
        <span data-kasir-total data-pos-order-total="{{ $order->total }}" data-pos-total-label>{{ $format::rupiah($order->total) }}</span>
    </div>
</div>
