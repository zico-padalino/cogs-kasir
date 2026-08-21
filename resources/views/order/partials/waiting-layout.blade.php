@props([
    'order',
    'format',
    'statusView',
])

<div class="order-waiting-layout" data-order-waiting>
    <div class="order-waiting-tabs order-view-tabs md:hidden" role="tablist" aria-label="Tampilan pembayaran">
        <button
            type="button"
            class="order-view-tab is-active"
            data-waiting-tab="pay"
            role="tab"
            aria-selected="true"
        >
            <span aria-hidden="true">💳</span>
            <span>Bayar</span>
        </button>
        <button
            type="button"
            class="order-view-tab"
            data-waiting-tab="order"
            role="tab"
            aria-selected="false"
        >
            <span aria-hidden="true">🧾</span>
            <span>Pesanan</span>
            <span class="order-view-badge">{{ $order->items->count() }}</span>
        </button>
    </div>

    <div class="order-waiting-panel is-active" data-waiting-panel="pay" role="tabpanel">
        @include($statusView, ['order' => $order, 'format' => $format])
    </div>

    <aside class="order-waiting-panel" data-waiting-panel="order" role="tabpanel">
        @include('order.partials.order-summary', [
            'order' => $order,
            'format' => $format,
            'editable' => false,
            'title' => 'Rincian Pesanan',
            'subtitle' => 'Cek item sebelum / saat bayar',
            'showMeta' => true,
        ])
    </aside>
</div>
