@extends('layouts.kasir')

@section('title', 'Kasir POS')
@section('heading', 'Kasir')
@section('body_class', 'is-kasir-pos')
@section('main_class', 'pos-main-wrap')

@section('content')
    <div id="kasir-pos" class="pos-shell">
        {{-- Toolbar --}}
        <header class="pos-toolbar">
            <div class="pos-toolbar-left">
                <div class="pos-order-chip">
                    <span class="pos-order-chip-label">Order</span>
                    <span class="pos-order-chip-value">{{ $order->order_number }}</span>
                </div>
                @if ($order->table)
                    <span class="pos-table-chip">{{ $order->table->label }}</span>
                @endif
                <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
            </div>
            <div class="pos-toolbar-right">
                <form action="{{ route('kasir.new-order') }}" method="POST">
                    @csrf
                    <button type="submit" class="pos-btn-ghost">+ Baru</button>
                </form>
            </div>
        </header>

        @if ($pendingOrders->isNotEmpty())
            <div class="pos-pending">
                <p class="pos-pending-title">Pesanan meja menunggu ({{ $pendingOrders->count() }})</p>
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
        @endif

        {{-- Mobile: Menu | Pesanan --}}
        <div class="pos-view-tabs lg:hidden" role="tablist">
            <button type="button" class="pos-view-tab is-active" data-kasir-tab="menu" role="tab" aria-selected="true">
                <span class="pos-view-tab-icon">🍽️</span>
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
            {{-- Panel Menu --}}
            <section class="pos-menu-panel kasir-panel-menu" data-kasir-panel="menu">
                <div class="pos-menu-head">
                    <h2 class="pos-panel-title">Menu</h2>
                    <input
                        type="search"
                        data-kasir-search
                        class="pos-search"
                        placeholder="Cari produk..."
                        autocomplete="off"
                    >
                </div>
                @include('kasir.partials.menu-grid', ['products' => $products, 'format' => $format])
            </section>

            {{-- Panel Pesanan --}}
            <aside class="pos-order-panel kasir-panel-cart hidden lg:flex" data-kasir-panel="cart">
                @include('kasir.partials.cart-panel', ['order' => $order, 'format' => $format])
            </aside>
        </div>

        @if ($order->items->isNotEmpty())
            <div class="pos-mobile-checkout lg:hidden">
                <div class="pos-mobile-checkout-info">
                    <span class="pos-mobile-checkout-label">{{ $order->items->count() }} item</span>
                    <span class="pos-mobile-checkout-total">{{ $format::rupiah($order->total) }}</span>
                </div>
                <button type="button" class="pos-mobile-checkout-btn" data-kasir-go-cart>Lihat Pesanan</button>
            </div>
        @endif
    </div>
@endsection
