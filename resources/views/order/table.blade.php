@extends('layouts.order-table')

@section('title', 'Pesan')

@php
    use App\Enums\PosOrderType;
    $orderTypes = PosOrderType::cases();
    $activeType = $order->order_type?->value ?? PosOrderType::Takeaway->value;
@endphp

@section('content')
    <div class="order-table-shell" data-order-table>
        <header class="order-table-header">
            @include('layouts.partials.shop-brand-mark', ['sizeClass' => 'h-11 w-11', 'roundedClass' => 'rounded-2xl', 'textClass' => 'text-lg'])
            <div class="order-header-copy">
                <p class="order-header-eyebrow">Pesan Online</p>
                <h1 class="order-header-shop">{{ config('pos.shop_name') }}</h1>
                <div class="order-header-meta">
                    <span class="order-header-number">{{ $order->order_number }}</span>
                    @if ($order->order_type)
                        <span>{{ $order->order_type->icon() }} {{ $order->order_type->label() }}</span>
                    @endif
                    @if ($order->customer_note)
                        <span class="truncate">{{ $order->customer_note }}</span>
                    @endif
                </div>
            </div>
            @if ($order->status->value === 'open' && $order->items->isNotEmpty())
                <div class="order-header-total lg:hidden">
                    <span class="text-[10px] uppercase tracking-wide text-slate-500">Total</span>
                    <span class="text-sm font-bold text-brand-600">{{ $format::rupiah($order->total) }}</span>
                </div>
            @endif
        </header>

        @if (session('success'))
            <div class="order-flash order-flash-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="order-flash order-flash-error">{{ session('error') }}</div>
        @endif

        <main class="order-table-main">
            @if ($order->status->value === 'submitted')
                @include('order.partials.kasir-confirmation', ['order' => $order, 'format' => $format])

                <div class="order-layout-single">
                    @include('order.partials.order-summary', ['order' => $order, 'format' => $format])
                </div>
            @elseif ($order->status->value === 'confirmed')
                @include('order.partials.kasir-confirmed', ['order' => $order, 'format' => $format])

                <div class="order-layout-single">
                    @include('order.partials.order-summary', ['order' => $order, 'format' => $format])
                </div>
            @elseif ($order->status->value === 'paid')
                @include('order.partials.paid-awaiting-serve', ['order' => $order, 'format' => $format])

                <div class="order-layout-single">
                    @include('order.partials.order-summary', ['order' => $order, 'format' => $format])
                </div>
            @elseif ($order->status->value === 'served')
                <div class="order-status-card order-status-paid">
                    <div class="order-status-icon">✅</div>
                    <h2 class="text-lg font-bold text-green-900">Pesanan Selesai</h2>
                    <p class="mt-2 text-sm text-green-800">Terima kasih! Pesanan Anda sudah diantar / selesai.</p>
                    <p class="mt-3 font-mono text-xs text-green-700">{{ $order->order_number }}</p>
                </div>

                <div class="order-layout-single">
                    @include('order.partials.order-summary', ['order' => $order, 'format' => $format])
                </div>

                @include('order.partials.new-order-button', [
                    'label' => '+ Pesan baru',
                    'hint' => 'Terima kasih! Tap di bawah untuk mulai pesanan baru.',
                    'confirm' => 'Mulai pesanan baru?',
                ])
            @else
                <div class="order-view-tabs lg:hidden" role="tablist">
                    <button type="button" class="order-view-tab is-active" data-order-tab="menu" role="tab" aria-selected="true">
                        <span aria-hidden="true">🍽️</span>
                        <span>Menu</span>
                    </button>
                    <button type="button" class="order-view-tab" data-order-tab="cart" role="tab" aria-selected="false">
                        <span aria-hidden="true">🛒</span>
                        <span>Pesanan Saya</span>
                        <span data-order-cart-badge class="order-view-badge {{ $order->items->isEmpty() ? 'hidden' : '' }}">{{ $order->items->count() }}</span>
                    </button>
                </div>

                <div class="order-table-layout">
                    <section class="order-panel-menu order-panel is-active" data-order-panel="menu">
                        <div class="order-section-head">
                            <h2 class="order-section-title">Pilih Menu</h2>
                            <p class="order-section-sub">Tap produk untuk atur jumlah, add-on, & catatan</p>
                        </div>

                        @include('order.partials.menu-grid', [
                            'products' => $products,
                            'format' => $format,
                        ])
                    </section>

                    <aside class="order-panel-cart order-panel" data-order-panel="cart">
                        <div class="order-cart-sticky">
                            @if ($order->items->isNotEmpty())
                                <div class="order-checkout-form">
                                    <div class="order-cart-scroll">
                                        @include('order.partials.order-summary', [
                                            'order' => $order,
                                            'format' => $format,
                                            'editable' => true,
                                        ])

                                        <form
                                            id="order-submit-form"
                                            action="{{ route('order.menu.submit') }}"
                                            method="POST"
                                            class="order-checkout-fields"
                                        >
                                            @csrf

                                            <div class="order-checkout-details">
                                                <p class="order-checkout-title">Sebelum kirim</p>
                                                <p class="order-checkout-hint">Pilih tipe pesanan dan isi nama — wajib sebelum dikirim ke kasir.</p>

                                                <div class="order-type-grid" role="radiogroup" aria-label="Tipe pesanan">
                                                    @foreach ($orderTypes as $orderType)
                                                        <label class="order-type-card {{ $activeType === $orderType->value ? 'is-active' : '' }}">
                                                            <input
                                                                type="radio"
                                                                name="order_type"
                                                                value="{{ $orderType->value }}"
                                                                class="sr-only"
                                                                required
                                                                @checked(old('order_type', $activeType) === $orderType->value)
                                                            >
                                                            <span class="order-type-icon" aria-hidden="true">{{ $orderType->icon() }}</span>
                                                            <span class="order-type-text">
                                                                <span class="order-type-name">{{ $orderType->label() }}</span>
                                                                <span class="order-type-desc">{{ $orderType->hint() }}</span>
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>

                                                <label class="order-checkout-label" for="order-customer-note">Nama pemesan</label>
                                                <input
                                                    id="order-customer-note"
                                                    type="text"
                                                    name="customer_note"
                                                    value="{{ old('customer_note', $order->customer_note) }}"
                                                    maxlength="255"
                                                    class="order-checkout-input"
                                                    placeholder="Contoh: Budi"
                                                    required
                                                    autocomplete="name"
                                                >
                                            </div>
                                        </form>

                                        <div class="order-submit-note">
                                            <p>Setelah dikirim, datang ke <strong>kasir</strong> untuk konfirmasi pesanan & pembayaran.</p>
                                        </div>
                                    </div>

                                    <div class="order-submit-wrap">
                                        <button
                                            type="button"
                                            class="btn-primary order-submit-btn"
                                            data-order-submit
                                        >
                                            Kirim ke Kasir
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="order-cart-scroll">
                                    @include('order.partials.order-summary', [
                                        'order' => $order,
                                        'format' => $format,
                                        'editable' => true,
                                    ])
                                    <p class="order-cart-hint">Tambah menu dulu, lalu isi tipe & nama sebelum kirim ke kasir.</p>
                                </div>
                            @endif
                        </div>
                    </aside>
                </div>
            @endif
        </main>

        <footer class="order-table-footer">
            <p>Pilih menu · Tipe & nama · Kirim · Bayar di kasir</p>
        </footer>
    </div>
@endsection
