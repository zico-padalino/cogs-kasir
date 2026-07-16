@extends('layouts.kasir')

@section('title', 'Detail Pesanan')
@section('heading', $order->order_number)

@section('content')
    <div class="mb-4 sm:mb-6">
        <a href="{{ route('kasir.orders') }}" class="page-back">← Kembali ke Riwayat</a>
        <h1 class="mt-2 hidden text-2xl font-bold md:block">{{ $order->order_number }}</h1>
        <p class="mt-1 text-sm text-slate-500">{{ $order->source->label() }} · {{ $order->table?->label ?? 'Walk-in' }}</p>
    </div>

    <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
        <x-table-card title="Item Pesanan">
            <table class="table-default">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Qty</th>
                        <th>Harga</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-product-image :product="$item->product" class="h-10 w-10 rounded-lg" />
                                    <div>
                                        <p class="font-medium">{{ $item->product->name }}</p>
                                        @if ($item->notes)
                                            <p class="text-xs text-amber-700">Catatan: {{ $item->notes }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $format::number($item->quantity, 0) }}</td>
                            <td>{{ $format::rupiah($item->unit_price) }}</td>
                            <td class="cell-money">{{ $format::rupiah($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <x-slot:footer>
                <p class="w-full text-lg font-bold">Total: {{ $format::rupiah($order->total) }}</p>
                @if ($order->status->value === 'paid')
                    <a href="{{ route('kasir.receipt', $order) }}" class="btn-primary w-full sm:w-auto">Lihat Struk</a>
                @endif
            </x-slot:footer>
        </x-table-card>

        <div class="card">
            <h2 class="font-semibold">Info Pembayaran</h2>
            <dl class="detail-meta mt-4">
                <div class="flex justify-between gap-4 sm:block">
                    <dt>Status</dt>
                    <dd><span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></dd>
                </div>
                <div class="flex justify-between gap-4 sm:block">
                    <dt>Metode</dt>
                    <dd>{{ $order->payment_method?->label() ?? '-' }}</dd>
                </div>
                <div class="flex justify-between gap-4 sm:block">
                    <dt>Kasir</dt>
                    <dd>{{ $order->cashierDisplayName() }}</dd>
                </div>
                <div class="flex justify-between gap-4 sm:block">
                    <dt>Dibayar</dt>
                    <dd>{{ $order->paid_at?->format('d/m/Y H:i') ?? '-' }}</dd>
                </div>
            </dl>
        </div>
    </div>
@endsection
