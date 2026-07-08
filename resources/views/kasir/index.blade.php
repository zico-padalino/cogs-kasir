@extends('layouts.kasir')

@section('title', 'Point of Sale')
@section('heading', 'Point of Sale')
@section('body_class', 'is-kasir-pos')
@section('main_class', 'pos-main-wrap')

@section('content')
    <div id="kasir-pos" class="pos-shell" data-pos-total="{{ $order->total }}">
        <header class="pos-toolbar">
            <button type="button" class="pos-toolbar-menu lg:hidden" data-mobile-menu-toggle aria-label="Buka menu navigasi">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <div class="pos-toolbar-left">
                <div class="pos-order-chip">
                    <span class="pos-order-chip-label">POS</span>
                    <span class="pos-order-chip-value">{{ $order->order_number }}</span>
                </div>
                @if ($order->order_type)
                    <span class="pos-type-chip max-lg:hidden" data-pos-toolbar-type>{{ $order->order_type->icon() }} {{ $order->order_type->label() }}</span>
                @else
                    <span class="pos-type-chip hidden" data-pos-toolbar-type></span>
                @endif
                @if ($order->customer_note)
                    <span class="pos-customer-chip max-lg:hidden" data-pos-toolbar-customer>{{ $order->customer_note }}</span>
                @else
                    <span class="pos-customer-chip hidden" data-pos-toolbar-customer></span>
                @endif
                <span class="badge max-lg:hidden {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
            </div>
            <div class="pos-toolbar-actions">
                @if ($order->isKasirEditable() && $order->items->isNotEmpty())
                    <form action="{{ route('kasir.order.cancel') }}" method="POST" class="pos-toolbar-action-form" onsubmit="return confirm('Batalkan pesanan ini?')">
                        @csrf
                        <button type="submit" class="pos-btn-danger">
                            <svg class="pos-btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span>Batal</span>
                        </button>
                    </form>
                @endif
                <form action="{{ route('kasir.new-order') }}" method="POST" class="pos-toolbar-action-form">
                    @csrf
                    <button type="submit" class="pos-btn-new" aria-label="Pesanan baru">
                        <svg class="pos-btn-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span class="hidden sm:inline">Pesanan Baru</span>
                    </button>
                </form>
            </div>
        </header>

        @if ($order->isKasirEditable())
            @include('kasir.partials.pos-order-bar', [
                'order' => $order,
                'orderTypes' => $orderTypes,
                'format' => $format,
            ])
        @endif

        <div data-pos-pending-wrap>
            @if ($pendingOrders->isNotEmpty())
                @include('kasir.partials.pending-orders', [
                    'pendingOrders' => $pendingOrders,
                    'format' => $format,
                ])
            @endif
        </div>

        <div class="pos-view-tabs lg:hidden" role="tablist">
            <button type="button" class="pos-view-tab is-active" data-kasir-tab="menu" role="tab" aria-selected="true">
                <span class="pos-view-tab-icon">☕</span>
                <span>Menu</span>
            </button>
            <button type="button" class="pos-view-tab" data-kasir-tab="cart" role="tab" aria-selected="false">
                <span class="pos-view-tab-icon">🧾</span>
                <span>Pesanan</span>
                @if ($order->items->isNotEmpty())
                    <span class="pos-view-tab-total">{{ $format::rupiah($order->total) }}</span>
                @endif
                <span data-kasir-cart-count class="pos-view-tab-badge {{ $order->items->isEmpty() ? 'hidden' : '' }}">{{ $order->items->count() }}</span>
            </button>
        </div>

        <div class="pos-workspace">
            <section class="pos-menu-panel kasir-panel-menu flex" data-kasir-panel="menu">
                <div class="pos-menu-head">
                    <div>
                        <h2 class="pos-panel-title">Menu</h2>
                        <p class="pos-panel-sub">Tap menu untuk tambah — tanpa scan barcode</p>
                    </div>
                    <input
                        type="search"
                        data-kasir-search
                        class="pos-search"
                        placeholder="Cari menu..."
                        autocomplete="off"
                    >
                </div>

                @if ($menuCategories !== [])
                    <div class="pos-category-tabs" role="tablist">
                        <button type="button" class="pos-category-tab is-active" data-kasir-category="all">Semua</button>
                        @foreach ($menuCategories as $category)
                            <button type="button" class="pos-category-tab" data-kasir-category="{{ $category }}">
                                {{ $menuCategoryLabels[$category] ?? ucfirst($category) }}
                            </button>
                        @endforeach
                    </div>
                @endif

                @include('kasir.partials.menu-grid', [
                    'products' => $products,
                    'format' => $format,
                    'menuCategoryLabels' => $menuCategoryLabels,
                ])
            </section>

            <aside class="pos-order-panel kasir-panel-cart hidden lg:flex" data-kasir-panel="cart">
                @include('kasir.partials.cart-panel', ['order' => $order, 'format' => $format])
            </aside>
        </div>

        @if ($order->items->isNotEmpty())
            <div class="pos-mobile-checkout lg:hidden" data-pos-mobile-checkout>
                <div class="pos-mobile-checkout-info">
                    <span class="pos-mobile-checkout-label">{{ $order->items->count() }} item</span>
                    <span class="pos-mobile-checkout-total" data-kasir-mobile-total>{{ $format::rupiah($order->total) }}</span>
                </div>
                <button type="button" class="pos-mobile-checkout-btn" data-kasir-go-cart>
                    <span data-kasir-go-cart-label>{{ $order->needsKasirConfirmation() ? 'Konfirmasi' : 'Bayar' }}</span>
                </button>
            </div>
        @endif
    </div>
@endsection
