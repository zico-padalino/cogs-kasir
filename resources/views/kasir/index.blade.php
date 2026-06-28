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
                @if ($order->table)
                    <span class="pos-table-chip max-lg:hidden" data-pos-toolbar-table>{{ $order->table->label }}</span>
                @else
                    <span class="pos-table-chip hidden" data-pos-toolbar-table></span>
                @endif
                @if ($order->customer_note)
                    <span class="pos-customer-chip max-lg:hidden" data-pos-toolbar-customer>{{ $order->customer_note }}</span>
                @else
                    <span class="pos-customer-chip hidden" data-pos-toolbar-customer></span>
                @endif
                <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
            </div>
            <div class="pos-toolbar-right">
                @if ($order->isKasirEditable() && $order->items->isNotEmpty())
                    <form action="{{ route('kasir.order.cancel') }}" method="POST" onsubmit="return confirm('Batalkan pesanan ini?')">
                        @csrf
                        <button type="submit" class="pos-btn-danger">Batal</button>
                    </form>
                @endif
                <form action="{{ route('kasir.new-order') }}" method="POST">
                    @csrf
                    <button type="submit" class="pos-btn-ghost">+ Baru</button>
                </form>
            </div>
        </header>

        @if ($order->isKasirEditable())
            @include('kasir.partials.pos-order-bar', [
                'order' => $order,
                'tables' => $tables,
                'orderTypes' => $orderTypes,
                'format' => $format,
            ])
        @endif

        @if ($pendingOrders->isNotEmpty())
            @php
                $pendingTotal = $pendingOrders->sum('total');
            @endphp
            <div class="pos-pending" data-pos-pending>
                <button type="button" class="pos-pending-toggle lg:hidden" data-pos-pending-toggle aria-expanded="false">
                    <span>🪑 {{ $pendingOrders->count() }} meja menunggu bayar · {{ $format::rupiah($pendingTotal) }}</span>
                    <span class="pos-pending-toggle-icon" aria-hidden="true">▼</span>
                </button>
                <div class="pos-pending-body" data-pos-pending-body>
                    <p class="pos-pending-title">Pesanan meja QR menunggu bayar ({{ $pendingOrders->count() }})</p>
                    <div class="pos-pending-list">
                        @foreach ($pendingOrders as $pending)
                            <form action="{{ route('kasir.load-order', $pending) }}" method="POST">
                                @csrf
                                <button type="submit" class="pos-pending-btn">
                                    <span>{{ $pending->table?->label ?? 'Online' }}</span>
                                    <span class="pos-pending-amount">{{ $format::rupiah($pending->total) }}</span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

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
            <section class="pos-menu-panel kasir-panel-menu" data-kasir-panel="menu">
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
                                {{ config('pos.menu_categories.'.$category, ucfirst($category)) }}
                            </button>
                        @endforeach
                    </div>
                @endif

                @include('kasir.partials.menu-grid', ['products' => $products, 'format' => $format])
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
                    <span data-kasir-go-cart-label>Bayar</span>
                </button>
            </div>
        @endif
    </div>
@endsection
