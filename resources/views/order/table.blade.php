@extends('layouts.order-table')

@section('title', 'Pesan')

@section('content')
    <div class="order-table-shell" data-order-table>
        <header class="order-table-header">
            <div class="order-table-header-badge">☕</div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">Pesan Online</p>
                <h1 class="truncate text-xl font-bold text-slate-900 sm:text-2xl">{{ config('pos.shop_name') }}</h1>
                <p class="text-xs text-slate-500">
                    <span class="font-mono">{{ $order->order_number }}</span>
                    @if ($order->customer_note)
                        · {{ $order->customer_note }}
                    @endif
                </p>
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
                <div class="order-status-card order-status-paid">
                    <div class="order-status-icon">✅</div>
                    <h2 class="text-lg font-bold text-green-900">Pesanan Lunas</h2>
                    <p class="mt-2 text-sm text-green-800">Terima kasih! Pembayaran sudah diterima di kasir.</p>
                    <p class="mt-3 font-mono text-xs text-green-700">{{ $order->order_number }}</p>
                </div>

                <div class="order-layout-single">
                    @include('order.partials.order-summary', ['order' => $order, 'format' => $format])
                </div>

                <form action="{{ route('order.menu.new') }}" method="POST" class="order-new-order-wrap">
                    @csrf
                    <button type="submit" class="btn-secondary w-full">+ Pesan Baru</button>
                </form>
            @else
                @if ($order->isEditable())
                    <div class="order-customer-card">
                        <form action="{{ route('order.menu.customer') }}" method="POST" class="order-customer-form">
                            @csrf
                            @method('PATCH')
                            <label class="order-customer-label" for="order-customer-note">Nama pemesan</label>
                            <p class="order-customer-hint">Wajib — kasir membedakan pesanan lewat nama & nomor order.</p>
                            <div class="order-customer-row">
                                <input
                                    id="order-customer-note"
                                    type="text"
                                    name="customer_note"
                                    value="{{ old('customer_note', $order->customer_note) }}"
                                    maxlength="255"
                                    class="order-customer-input"
                                    placeholder="Contoh: Budi"
                                    required
                                    autocomplete="name"
                                >
                                <button type="submit" class="btn-primary order-customer-save">Simpan</button>
                            </div>
                        </form>
                    </div>
                @endif

                <div class="order-view-tabs lg:hidden" role="tablist">
                    <button type="button" class="order-view-tab is-active" data-order-tab="menu" role="tab" aria-selected="true">
                        Menu
                    </button>
                    <button type="button" class="order-view-tab" data-order-tab="cart" role="tab" aria-selected="false">
                        Pesanan
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
                            @include('order.partials.order-summary', [
                                'order' => $order,
                                'format' => $format,
                                'editable' => true,
                            ])

                            @if ($order->items->isNotEmpty())
                                <div class="order-submit-note">
                                    <p>Setelah dikirim, datang ke <strong>kasir</strong> untuk konfirmasi pesanan & pembayaran.</p>
                                </div>
                                <form action="{{ route('order.menu.submit') }}" method="POST" class="order-submit-wrap">
                                    @csrf
                                    <button type="submit" class="btn-primary order-submit-btn" onclick="return confirm('Kirim pesanan ke kasir? Setelah ini, silakan ke kasir untuk konfirmasi dan pembayaran.')">
                                        Kirim ke Kasir
                                    </button>
                                </form>
                            @else
                                <p class="order-cart-hint">Tambah menu dulu, lalu kirim pesanan ke kasir.</p>
                            @endif
                        </div>
                    </aside>
                </div>
            @endif
        </main>

        <footer class="order-table-footer">
            <p>Isi nama · Pilih menu · Kirim pesanan · Bayar di kasir</p>
        </footer>
    </div>
@endsection
