@extends('layouts.kasir')

@section('title', 'Riwayat Pesanan')
@section('heading', 'Riwayat Pesanan')

@section('content')
    <h1 class="mb-4 hidden text-2xl font-bold md:block sm:mb-6">Riwayat Pesanan</h1>

    <x-table-card title="Semua Pesanan Kasir & Online">
        @if ($orders->isNotEmpty())
            {{-- Mobile: kartu ringkas --}}
            <div class="orders-mobile-list md:hidden">
                @foreach ($orders as $order)
                    <article class="orders-mobile-card">
                        <div class="orders-mobile-card-top">
                            <div class="min-w-0">
                                <p class="orders-mobile-name">{{ $order->customer_note ?: 'Tanpa nama' }}</p>
                                <p class="orders-mobile-meta">
                                    {{ $order->order_number }}
                                    · {{ $order->source->label() }}
                                    @if ($order->table)
                                        · {{ $order->table->label }}
                                    @endif
                                </p>
                            </div>
                            <span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
                        </div>
                        <div class="orders-mobile-card-money">
                            <p class="orders-mobile-total">{{ $format::rupiah($order->total) }}</p>
                            @if ($order->hasDiscount())
                                <p class="orders-mobile-discount">Diskon -{{ $format::rupiah($order->discount_amount) }}</p>
                            @endif
                            <p class="orders-mobile-time">{{ $order->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                        <div class="orders-mobile-card-actions">
                            <a href="{{ route('kasir.orders.show', $order) }}" class="btn-sm btn-ghost text-brand-700">Detail</a>
                            @if (in_array($order->status->value, ['paid', 'served'], true))
                                <a href="{{ route('kasir.receipt', $order) }}" class="btn-sm btn-outline">Struk</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            {{-- Desktop: tabel lengkap --}}
            <div class="hidden md:block">
                <table class="table-default">
                    <thead>
                        <tr>
                            <th>No. Order</th>
                            <th>Pemesan</th>
                            <th>Sumber</th>
                            <th>Meja</th>
                            <th>Harga normal</th>
                            <th>Diskon</th>
                            <th>Total bayar</th>
                            <th>Status</th>
                            <th>Waktu</th>
                            <th class="col-actions">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td class="font-mono text-xs">{{ $order->order_number }}</td>
                                <td>{{ $order->customer_note ?: '-' }}</td>
                                <td>{{ $order->source->label() }}</td>
                                <td>{{ $order->table?->label ?? '-' }}</td>
                                <td class="cell-money {{ $order->hasDiscount() ? 'text-slate-500' : '' }}">
                                    {{ $format::rupiah($order->subtotal) }}
                                </td>
                                <td class="{{ $order->hasDiscount() ? 'font-medium text-amber-700' : 'cell-muted' }}">
                                    @if ($order->hasDiscount())
                                        -{{ $format::rupiah($order->discount_amount) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="cell-money">{{ $format::rupiah($order->total) }}</td>
                                <td><span class="badge {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                                <td class="text-xs cell-muted">{{ $order->created_at->format('d/m/Y H:i') }}</td>
                                <td class="col-actions">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                                        <a href="{{ route('kasir.orders.show', $order) }}" class="btn-sm btn-ghost text-brand-700">Detail</a>
                                        @if (in_array($order->status->value, ['paid', 'served'], true))
                                            <a href="{{ route('kasir.receipt', $order) }}" class="btn-sm btn-outline">Struk</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-slot:footer>
                <div class="pagination-wrap w-full">{{ $orders->links() }}</div>
            </x-slot:footer>
        @else
            <div class="empty-state"><p>Belum ada pesanan.</p></div>
        @endif
    </x-table-card>
@endsection
