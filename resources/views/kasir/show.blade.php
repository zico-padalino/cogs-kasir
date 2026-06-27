@extends('layouts.kasir')

@section('title', 'Detail Pesanan')

@section('content')
    <div class="mb-6">
        <a href="{{ route('kasir.orders') }}" class="text-sm text-brand-600">← Kembali</a>
        <h1 class="mt-2 text-2xl font-bold">{{ $order->order_number }}</h1>
        <p class="text-sm text-slate-500">{{ $order->source->label() }} · {{ $order->table?->label ?? 'Walk-in' }}</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
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
                            <td class="font-medium">{{ $item->product->name }}</td>
                            <td>{{ $format::number($item->quantity, 0) }}</td>
                            <td>{{ $format::rupiah($item->unit_price) }}</td>
                            <td class="cell-money">{{ $format::rupiah($item->line_total) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <x-slot:footer>
                <p class="text-lg font-bold">Total: {{ $format::rupiah($order->total) }}</p>
                @if ($order->status->value === 'paid')
                    <a href="{{ route('kasir.receipt', $order) }}" class="btn-primary">Lihat Struk</a>
                @endif
            </x-slot:footer>
        </x-table-card>

        <div class="card">
            <h2 class="font-semibold">Info Pembayaran</h2>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt>Status</dt><dd><span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></dd></div>
                <div class="flex justify-between"><dt>Metode</dt><dd>{{ $order->payment_method?->label() ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt>Kasir</dt><dd>{{ $order->cashier?->name ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt>Dibayar</dt><dd>{{ $order->paid_at?->format('d/m/Y H:i') ?? '-' }}</dd></div>
            </dl>
        </div>
    </div>
@endsection
