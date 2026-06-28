@extends('layouts.order-table')

@section('title', $table->label)

@section('content')
    <div class="order-table-shell" data-order-table>
        <header class="order-table-header">
            <div class="order-table-header-badge">🍽️</div>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">Menu Meja</p>
                <h1 class="truncate text-xl font-bold text-slate-900 sm:text-2xl">{{ $table->label }}</h1>
                <p class="text-xs text-slate-500">Meja #{{ $table->table_number }} · Pesan dari HP atau tablet</p>
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
                <div class="order-status-card order-status-waiting">
                    <div class="order-status-icon">💳</div>
                    <h2 class="text-lg font-bold text-slate-900">Silakan Bayar di Kasir</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">
                        Pesanan Anda sudah masuk ke kasir. Datang ke kasir dan sebutkan nomor pesanan di bawah.
                    </p>
                    <div class="order-status-meta">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Nomor Pesanan</p>
                            <p class="font-mono text-sm font-bold text-slate-900">{{ $order->order_number }}</p>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wide text-slate-500">Meja</p>
                            <p class="text-sm font-semibold">{{ $table->label }}</p>
                        </div>
                    </div>
                </div>

                <div class="order-layout-single">
                    @include('order.partials.order-summary', ['order' => $order, 'format' => $format])
                </div>

                <div class="order-info-box">
                    <p class="font-semibold text-amber-900">Menunggu pembayaran</p>
                    <p class="mt-1 text-sm text-amber-800">Kasir akan memproses pesanan ini. Stok berkurang setelah pembayaran.</p>
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
            @else
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
                            <p class="order-section-sub">Tap produk untuk atur jumlah & catatan pembelian</p>
                        </div>

                        @include('order.partials.menu-grid', [
                            'products' => $products,
                            'table' => $table,
                            'format' => $format,
                        ])
                    </section>

                    <aside class="order-panel-cart order-panel" data-order-panel="cart">
                        <div class="order-cart-sticky">
                            @include('order.partials.order-summary', [
                                'order' => $order,
                                'format' => $format,
                                'editable' => true,
                                'table' => $table,
                            ])

                            @if ($order->items->isNotEmpty())
                                <form action="{{ route('order.table.submit', $table->barcode_token) }}" method="POST" class="order-submit-wrap">
                                    @csrf
                                    <button type="submit" class="btn-primary order-submit-btn" onclick="return confirm('Kirim pesanan ke kasir? Setelah dikirim, bayar di kasir.')">
                                        Kirim Pesanan · Bayar di Kasir
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
            <p>Hanya untuk {{ $table->label }} · Scan ulang QR meja jika pindah tempat duduk</p>
        </footer>
    </div>
@endsection
