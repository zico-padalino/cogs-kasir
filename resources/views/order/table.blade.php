@extends('layouts.guest')

@section('title', $table->label)

@section('content')
    <div class="min-h-screen bg-gradient-to-b from-brand-50 to-slate-100 px-4 py-6">
        <div class="mx-auto max-w-lg">
            <div class="mb-6 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-2xl text-white">🍽️</div>
                <h1 class="mt-3 text-2xl font-bold text-slate-900">{{ $table->label }}</h1>
                <p class="text-sm text-slate-500">Pesan langsung dari meja Anda</p>
            </div>

            @if (session('success'))
                <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">✓ {{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
            @endif

            @if ($order->status->value === 'submitted')
                <div class="alert-tip mb-6 text-center">
                    Pesanan sudah dikirim ke kasir. Silakan tunggu konfirmasi pembayaran.
                </div>
            @elseif ($order->status->value === 'paid')
                <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-center text-sm text-green-800">
                    Pesanan lunas. Terima kasih!
                </div>
            @else
                <div class="mb-6 space-y-3">
                    <h2 class="font-semibold text-slate-800">Menu</h2>
                    @forelse ($products as $product)
                        <form action="{{ route('order.table.items', $table->barcode_token) }}" method="POST" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-slate-900">{{ $product->name }}</p>
                                <p class="text-sm text-brand-600">{{ $product->selling_price > 0 ? $format::rupiah($product->selling_price) : $format::rupiah($product->standard_cost) }}</p>
                            </div>
                            <input type="number" name="quantity" value="1" min="1" max="{{ (int) $product->availableQuantity() }}" class="form-input w-16 text-center">
                            <button type="submit" class="btn-primary btn-sm shrink-0">+</button>
                        </form>
                    @empty
                        <p class="text-center text-sm text-slate-500">Menu belum tersedia.</p>
                    @endforelse
                </div>

                @if ($order->items->isNotEmpty())
                    <div class="card mb-4">
                        <h2 class="mb-3 font-semibold">Pesanan Anda</h2>
                        @foreach ($order->items as $item)
                            <div class="flex justify-between border-b border-slate-100 py-2 text-sm last:border-0">
                                <span>{{ $item->product->name }} × {{ $format::number($item->quantity, 0) }}</span>
                                <span class="font-medium">{{ $format::rupiah($item->line_total) }}</span>
                            </div>
                        @endforeach
                        <div class="mt-3 flex justify-between font-bold">
                            <span>Total</span>
                            <span class="text-brand-600">{{ $format::rupiah($order->total) }}</span>
                        </div>
                    </div>

                    <form action="{{ route('order.table.submit', $table->barcode_token) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-primary w-full py-3" onclick="return confirm('Kirim pesanan ke kasir?')">
                            Kirim Pesanan ke Kasir
                        </button>
                    </form>
                @endif
            @endif
        </div>
    </div>
@endsection
