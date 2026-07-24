@php
    use App\Enums\PosOrderSource;
@endphp

<div @class(['pos-receipt', 'is-checkout-ready' => $order->canCheckoutAtKasir()])>
    <div class="pos-receipt-head">
        <button
            type="button"
            class="pos-receipt-back lg:hidden"
            data-kasir-go-menu
            aria-label="Kembali ke menu"
        >
            ← Menu
        </button>
        <div class="pos-receipt-head-copy">
            <h2 class="pos-receipt-title">Keranjang</h2>
            <p class="pos-receipt-meta">{{ $order->items->count() }} item · {{ $order->order_number }}</p>
        </div>
        <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
    </div>

    @if ($order->isOpenBill())
        <div class="pos-open-bill-hint">
            Tagihan terbuka — boleh tambah item. Stok dipotong saat bayar.
        </div>
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
            @if ($order->canChecklistDelivered())
                @php
                    $cartDelivered = $order->items->where('is_delivered', true)->count();
                    $cartItemCount = $order->items->count();
                    $deliverItems = $order->items->map(fn ($item) => [
                        'id' => (int) $item->id,
                        'name' => $item->product?->name ?? 'Item',
                        'qty' => (float) $item->quantity,
                        'is_delivered' => (bool) $item->is_delivered,
                        'url' => route('kasir.items.delivered', $item),
                    ])->values()->all();
                @endphp
                <button
                    type="button"
                    class="pos-deliver-open-btn"
                    data-deliver-open
                    data-deliver-title="{{ $order->customer_note ?: $order->order_number }}"
                >
                    <span hidden data-deliver-payload>@json($deliverItems)</span>
                    <span class="pos-deliver-open-btn-label">Ceklis antar</span>
                    <span class="pos-deliver-open-btn-progress" data-deliver-progress>
                        <span data-deliver-done>{{ $cartDelivered }}</span>/<span data-deliver-total>{{ $cartItemCount }}</span>
                    </span>
                </button>
                <p class="pos-deliver-open-hint">Tandai item yang sudah diantar ke meja / pelanggan.</p>
            @endif
            <div class="pos-receipt-lines">
                @foreach ($order->items as $item)
                    <x-pos-order-item
                        :item="$item"
                        :format="$format"
                        :editable="$order->isKasirEditable()"
                        :can-deliver="false"
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
                @include('kasir.partials.order-totals', ['order' => $order, 'format' => $format, 'totalLabel' => 'Total'])
            </div>

            <p class="pos-receipt-pay-guide lg:hidden">Cek item → atur diskon → bayar atau simpan dulu</p>

            <div class="pos-receipt-pay-actions">
                <button type="button" class="pos-pay-submit" data-kasir-open-pay data-kasir-pay-button>
                    Bayar <span data-kasir-pay-button-total>{{ $format::rupiah($order->total) }}</span>
                </button>
                @if ($order->source === PosOrderSource::Kasir)
                    <form action="{{ route('kasir.open-bill') }}" method="POST" class="pos-hold-form">
                        @csrf
                        <button
                            type="submit"
                            class="pos-hold-submit"
                            onclick="return confirm({{ json_encode($order->isOpenBill() ? 'Simpan perubahan tagihan terbuka?' : 'Simpan dulu (bayar nanti)? Bisa dibuka lagi untuk tambah item. Stok belum dipotong.') }})"
                        >
                            {{ $order->isOpenBill() ? 'Update tagihan' : 'Simpan dulu' }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @elseif ($order->items->isNotEmpty())
        <div class="pos-receipt-foot">
            @include('kasir.partials.order-totals', ['order' => $order, 'format' => $format, 'totalLabel' => 'Total'])
        </div>
    @endif
</div>

<x-product-image-lightbox />
