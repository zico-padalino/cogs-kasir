@extends('layouts.kasir')

@section('title', 'Kasir POS')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Point of Sale</h1>
            <p class="text-sm text-slate-500">Order: <span class="font-mono font-semibold">{{ $order->order_number }}</span></p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form action="{{ route('kasir.new-order') }}" method="POST">
                @csrf
                <button type="submit" class="btn-secondary btn-sm">+ Order Baru</button>
            </form>
        </div>
    </div>

    @if ($pendingOrders->isNotEmpty())
        <div class="alert-tip mb-6">
            <p class="font-semibold">Pesanan online menunggu ({{ $pendingOrders->count() }})</p>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($pendingOrders as $pending)
                    <form action="{{ route('kasir.load-order', $pending) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-primary btn-sm">
                            {{ $pending->table?->label ?? 'Meja' }} · {{ $format::rupiah($pending->total) }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-12">
        {{-- Produk --}}
        <div class="xl:col-span-7">
            <div class="table-card">
                <div class="table-card-header">
                    <h2 class="text-base font-semibold">Menu Produk</h2>
                    <p class="text-xs text-slate-500">Hanya barang jadi dengan stok tersedia (sinkron COGS)</p>
                </div>
                <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($products as $product)
                        <form action="{{ route('kasir.items.store') }}" method="POST" class="pos-product-card">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="w-full rounded-xl border border-slate-200 bg-white p-4 text-left transition hover:border-brand-400 hover:shadow-md">
                                <p class="font-semibold text-slate-900">{{ $product->name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $product->sku }}</p>
                                <div class="mt-3 flex items-center justify-between">
                                    <span class="font-bold text-brand-600">
                                        {{ $product->selling_price > 0 ? $format::rupiah($product->selling_price) : 'Atur harga' }}
                                    </span>
                                    <span class="badge badge-slate">Stok {{ $format::number($product->availableQuantity(), 0) }}</span>
                                </div>
                            </button>
                        </form>
                    @empty
                        <p class="col-span-full px-4 py-8 text-center text-sm text-slate-500">
                            Tidak ada produk siap jual. Buat produksi & stok barang jadi di modul COGS dulu.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Keranjang --}}
        <div class="xl:col-span-5">
            <div class="table-card sticky top-24">
                <div class="table-card-header">
                    <div>
                        <h2 class="text-base font-semibold">Keranjang</h2>
                        <p class="text-xs text-slate-500">{{ $order->items->count() }} item</p>
                    </div>
                    <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                </div>

                @if ($order->items->isNotEmpty())
                    <div class="divide-y divide-slate-100">
                        @foreach ($order->items as $item)
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium text-slate-900">{{ $item->product->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $format::number($item->quantity, 0) }} × {{ $format::rupiah($item->unit_price) }}</p>
                                </div>
                                <p class="font-semibold">{{ $format::rupiah($item->line_total) }}</p>
                                <form action="{{ route('kasir.items.destroy', $item) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn-outline-danger btn-sm">×</button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="mb-4 flex justify-between text-lg font-bold">
                            <span>Total</span>
                            <span class="text-brand-600">{{ $format::rupiah($order->total) }}</span>
                        </div>

                        <form action="{{ route('kasir.pay') }}" method="POST" class="space-y-3">
                            @csrf
                            <div>
                                <label class="form-label">Metode Bayar</label>
                                <select name="payment_method" class="form-input" required>
                                    @foreach (\App\Enums\PaymentMethod::cases() as $method)
                                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn-primary w-full py-3 text-base" onclick="return confirm('Proses pembayaran? Stok akan berkurang otomatis.')">
                                Bayar & Cetak Struk
                            </button>
                        </form>
                    </div>
                @else
                    <div class="empty-state">
                        <p>Keranjang kosong</p>
                        <p class="empty-hint">Klik produk di kiri untuk menambah item</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
