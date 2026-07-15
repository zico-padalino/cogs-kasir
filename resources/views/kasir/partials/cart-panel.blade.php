@php
    use App\Enums\PosOrderSource;
    $isOnline = $order->source === PosOrderSource::Online;
    $step = match (true) {
        $order->status->value === 'paid' => 4,
        $order->canCheckoutAtKasir() => 3,
        $isOnline => 2,
        default => 1,
    };
@endphp

<div class="pos-receipt">
    <div class="pos-receipt-head">
        <div>
            <h2 class="pos-receipt-title">Pesanan</h2>
            <p class="pos-receipt-meta">{{ $order->items->count() }} item · {{ $order->order_number }}</p>
        </div>
        <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
    </div>

    @if ($isOnline)
        <nav class="pos-flow-steps" aria-label="Alur pesanan">
            <div class="pos-flow-step {{ $step >= 1 ? 'is-done' : '' }} {{ $step === 1 ? 'is-current' : '' }}">
                <span class="pos-flow-step-num">1</span>
                <span class="pos-flow-step-label">Pesan</span>
            </div>
            <div class="pos-flow-step {{ $step >= 2 ? 'is-done' : '' }} {{ $step === 2 ? 'is-current' : '' }}">
                <span class="pos-flow-step-num">2</span>
                <span class="pos-flow-step-label">Kasir</span>
            </div>
            <div class="pos-flow-step {{ $step >= 3 ? 'is-done' : '' }} {{ $step === 3 ? 'is-current' : '' }}">
                <span class="pos-flow-step-num">3</span>
                <span class="pos-flow-step-label">Bayar</span>
            </div>
            <div class="pos-flow-step {{ $step >= 4 ? 'is-done' : '' }} {{ $step === 4 ? 'is-current' : '' }}">
                <span class="pos-flow-step-num">4</span>
                <span class="pos-flow-step-label">Selesai</span>
            </div>
        </nav>
    @endif

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
                <p class="font-medium text-slate-600">Belum ada item</p>
                <p class="pos-receipt-empty-hint">Pilih menu untuk mulai pesanan</p>
            </div>
        @endif
    </div>

    @if ($order->items->isNotEmpty() && $order->canCheckoutAtKasir())
        @include('kasir.partials.discount-panel', ['order' => $order, 'format' => $format])

        <div class="pos-receipt-pay" data-pos-receipt-pay>
            <div class="pos-receipt-pay-totals">
                @include('kasir.partials.order-totals', ['order' => $order, 'format' => $format, 'totalLabel' => 'Total Tagihan'])
            </div>

            @if ($isOnline)
                <p class="pos-pay-flow-hint">Langkah 3: terima pembayaran, lalu pesanan selesai.</p>
            @endif

            <button type="button" class="pos-pay-submit" data-kasir-open-pay data-kasir-pay-button>
                Bayar <span data-kasir-pay-button-total>{{ $format::rupiah($order->total) }}</span>
            </button>
        </div>
    @elseif ($order->items->isNotEmpty())
        <div class="pos-receipt-foot">
            @include('kasir.partials.order-totals', ['order' => $order, 'format' => $format, 'totalLabel' => 'Total'])
        </div>
    @endif
</div>

<x-product-image-lightbox />
