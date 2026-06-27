@extends('layouts.kasir')

@section('title', 'Kasir POS')
@section('heading', 'Point of Sale')

@section('content')
    <div id="kasir-pos" class="kasir-pos">
        <div class="page-actions mb-4 sm:mb-6">
            <div class="min-w-0">
                <h1 class="hidden text-2xl font-bold text-slate-900 md:block">Point of Sale</h1>
                <p class="text-sm text-slate-500">
                    Order: <span class="font-mono font-semibold text-slate-800">{{ $order->order_number }}</span>
                </p>
            </div>
            <div class="page-actions-group">
                <form action="{{ route('kasir.new-order') }}" method="POST" class="w-full sm:w-auto">
                    @csrf
                    <button type="submit" class="btn-secondary btn-sm w-full sm:w-auto">+ Order Baru</button>
                </form>
            </div>
        </div>

        @if ($pendingOrders->isNotEmpty())
            <div class="alert-tip mb-4 sm:mb-6">
                <p class="font-semibold">Pesanan online menunggu ({{ $pendingOrders->count() }})</p>
                <div class="mt-3 grid gap-2 sm:flex sm:flex-wrap">
                    @foreach ($pendingOrders as $pending)
                        <form action="{{ route('kasir.load-order', $pending) }}" method="POST" class="w-full sm:w-auto">
                            @csrf
                            <button type="submit" class="btn-primary btn-sm w-full justify-center sm:w-auto">
                                {{ $pending->table?->label ?? 'Meja' }} · {{ $format::rupiah($pending->total) }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Tab mobile: Menu | Keranjang --}}
        <div class="kasir-tabs xl:hidden" role="tablist" aria-label="Menu dan keranjang">
            <button type="button" class="kasir-tab is-active" data-kasir-tab="menu" role="tab" aria-selected="true">
                <span>Menu</span>
            </button>
            <button type="button" class="kasir-tab" data-kasir-tab="cart" role="tab" aria-selected="false">
                <span>Keranjang</span>
                <span data-kasir-cart-count class="kasir-tab-badge {{ $order->items->isEmpty() ? 'hidden' : '' }}">{{ $order->items->count() }}</span>
            </button>
        </div>

        <div class="grid gap-4 sm:gap-6 xl:grid-cols-12">
            {{-- Produk --}}
            <div class="kasir-panel-menu xl:col-span-7" data-kasir-panel="menu">
                <div class="table-card">
                    <div class="table-card-header">
                        <div>
                            <h2 class="text-base font-semibold">Menu Produk</h2>
                            <p class="text-xs text-slate-500">Barang jadi dengan stok tersedia</p>
                        </div>
                    </div>
                    <div class="pos-product-grid">
                        @forelse ($products as $product)
                            <form action="{{ route('kasir.items.store') }}" method="POST" class="pos-product-card">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="pos-product-btn">
                                    <p class="line-clamp-2 font-semibold text-slate-900">{{ $product->name }}</p>
                                    <p class="mt-0.5 text-[11px] text-slate-500">{{ $product->sku }}</p>
                                    <div class="mt-2 flex items-end justify-between gap-2">
                                        <span class="text-sm font-bold text-brand-600 sm:text-base">
                                            {{ $product->selling_price > 0 ? $format::rupiah($product->selling_price) : 'Atur harga' }}
                                        </span>
                                        <span class="badge badge-slate shrink-0">Stok {{ $format::number($product->availableQuantity(), 0) }}</span>
                                    </div>
                                </button>
                            </form>
                        @empty
                            <p class="pos-product-empty">
                                Tidak ada produk siap jual. Buat produksi & stok barang jadi di modul COGS dulu.
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Keranjang --}}
            <div class="kasir-panel-cart hidden xl:col-span-5 xl:block" data-kasir-panel="cart">
                @include('kasir.partials.cart-panel', ['order' => $order, 'format' => $format])
            </div>
        </div>

        @if ($order->items->isNotEmpty())
            <div class="kasir-cart-bar xl:hidden">
                <div class="kasir-cart-bar-inner">
                    <div class="min-w-0">
                        <p class="text-xs text-slate-500">{{ $order->items->count() }} item</p>
                        <p class="truncate text-base font-bold text-brand-600">{{ $format::rupiah($order->total) }}</p>
                    </div>
                    <button type="button" class="btn-primary shrink-0" data-kasir-go-cart>
                        Bayar
                    </button>
                </div>
            </div>
        @endif
    </div>
@endsection
