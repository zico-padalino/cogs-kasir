@extends('layouts.kasir')

@section('title', 'Riwayat Pesanan')

@section('content')
    <h1 class="mb-6 text-2xl font-bold">Riwayat Pesanan</h1>

    <x-table-card title="Semua Pesanan Kasir & Online">
        @if ($orders->isNotEmpty())
            <table class="table-default">
                <thead>
                    <tr>
                        <th>No. Order</th>
                        <th>Sumber</th>
                        <th>Meja</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Waktu</th>
                        <th class="col-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td class="font-mono text-xs">{{ $order->order_number }}</td>
                            <td>{{ $order->source->label() }}</td>
                            <td>{{ $order->table?->label ?? '-' }}</td>
                            <td class="cell-money">{{ $format::rupiah($order->total) }}</td>
                            <td><span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                            <td class="text-xs cell-muted">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                            <td class="col-actions">
                                <a href="{{ route('kasir.orders.show', $order) }}" class="btn-sm btn-ghost text-brand-700">Detail</a>
                                @if ($order->status->value === 'paid')
                                    <a href="{{ route('kasir.receipt', $order) }}" class="btn-sm btn-outline">Struk</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <x-slot:footer>
                <div class="pagination-wrap w-full">{{ $orders->links() }}</div>
            </x-slot:footer>
        @else
            <div class="empty-state"><p>Belum ada pesanan.</p></div>
        @endif
    </x-table-card>
@endsection
