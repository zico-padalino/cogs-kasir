@extends('layouts.kasir')

@section('title', 'Pembukuan')
@section('heading', 'Pembukuan')
@section('subheading', 'Ringkasan penjualan lunas per hari')

@section('content')
    <div class="page-toolbar">
        <p class="text-sm text-slate-500">Omzet &amp; rincian transaksi berdasarkan tanggal bayar</p>
        <a href="{{ route('kasir.orders') }}" class="btn-outline btn-sm shrink-0">Riwayat Pesanan</a>
    </div>

    <form method="GET" action="{{ route('kasir.pembukuan.index') }}" class="pembukuan-filter card">
        <label class="form-label" for="pembukuan-date">Tanggal</label>
        <div class="pembukuan-filter-row">
            <input
                id="pembukuan-date"
                type="date"
                name="date"
                value="{{ $date->toDateString() }}"
                class="form-input"
                required
            >
            <button type="submit" class="btn-primary shrink-0">Tampilkan</button>
            @if (! $date->isToday())
                <a href="{{ route('kasir.pembukuan.index') }}" class="btn-outline shrink-0">Hari ini</a>
            @endif
        </div>
    </form>

    <div class="pembukuan-stats">
        <x-stat-card label="Omzet" :value="$format::rupiah($omzet)" color="green" />
        <x-stat-card label="Transaksi" :value="$format::number($count, 0)" color="brand" />
        <x-stat-card label="Rata-rata" :value="$format::rupiah($average)" color="slate" />
    </div>

    <div class="pembukuan-payments">
        @foreach ($byPayment as $payment)
            <div class="pembukuan-payment-card">
                <p class="pembukuan-payment-label">{{ $payment['label'] }}</p>
                <p class="pembukuan-payment-total">{{ $format::rupiah($payment['total']) }}</p>
                <p class="pembukuan-payment-count">{{ $payment['count'] }} transaksi</p>
            </div>
        @endforeach
    </div>

    <x-table-card :title="'Pesanan lunas · '.$date->translatedFormat('d M Y')">
        @if ($orders->isNotEmpty())
            <table class="table-default">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>No. Order</th>
                        <th>Sumber</th>
                        <th>Metode</th>
                        <th>Total</th>
                        <th class="col-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td class="text-xs cell-muted">{{ $order->paid_at?->format('H:i') ?? '-' }}</td>
                            <td class="font-mono text-xs">{{ $order->order_number }}</td>
                            <td>{{ $order->source->label() }}{{ $order->table ? ' · '.$order->table->label : '' }}</td>
                            <td>{{ $order->payment_method?->label() ?? '-' }}</td>
                            <td class="cell-money">{{ $format::rupiah($order->total) }}</td>
                            <td class="col-actions">
                                <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                                    <a href="{{ route('kasir.orders.show', $order) }}" class="btn-sm btn-ghost text-brand-700">Detail</a>
                                    <a href="{{ route('kasir.receipt', $order) }}" class="btn-sm btn-outline">Struk</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-state">
                <p>Belum ada penjualan lunas pada tanggal ini.</p>
                <p class="empty-hint">Ubah tanggal filter atau selesaikan pembayaran di POS.</p>
            </div>
        @endif
    </x-table-card>
@endsection
