@extends('layouts.app')

@section('title', 'Produksi')
@section('heading', 'Langkah 5: Proses Produksi')
@section('subheading', 'Buat jadwal → mulai kerja → selesai, biaya terhitung otomatis')

@section('content')
    <x-step-header number="5" title="Proses Produksi"
        description="1) Buat jadwal produksi  2) Klik Mulai  3) Klik Selesai — sistem hitung biaya dari resep dan stok." />

    <div class="page-toolbar">
        <p class="text-sm text-slate-500">Kelola produksi dari antrian sampai selesai.</p>
        <a href="{{ route('production-orders.create') }}" class="btn-primary shrink-0">+ Buat Produksi Baru</a>
    </div>

    <x-table-card title="Daftar Produksi" subtitle="{{ $orders->total() }} jadwal">
        @if ($orders->isNotEmpty())
            <table class="table-default">
                <thead>
                    <tr>
                        <th>No. Produksi</th>
                        <th>Produk</th>
                        <th>Jumlah</th>
                        <th>Status</th>
                        <th class="col-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td class="font-mono text-xs cell-muted">{{ $order->order_number }}</td>
                            <td class="font-semibold text-slate-900">{{ $order->product->name }}</td>
                            <td>{{ number_format($order->quantity_planned, 0) }} {{ $order->product->unit }}</td>
                            <td>
                                @php
                                    $badges = [
                                        'draft' => ['Belum dimulai', 'badge-slate'],
                                        'in_progress' => ['Sedang jalan', 'badge-blue'],
                                        'completed' => ['Selesai', 'badge-green'],
                                    ];
                                    [$label, $badgeClass] = $badges[$order->status->value] ?? ['?', 'badge-slate'];
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ $label }}</span>
                            </td>
                            <td class="col-actions">
                                <x-crud-actions
                                    :show="route('production-orders.show', $order)"
                                    :edit="$order->status->value === 'draft' ? route('production-orders.edit', $order) : null"
                                    :delete="$order->status->value !== 'completed' ? route('production-orders.destroy', $order) : null"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <x-slot:footer>
                <p class="text-sm text-slate-500">Produksi selesai? Lihat hasil perhitungan biaya.</p>
                <a href="{{ route('cogs.history') }}" class="btn-secondary">Lihat Hasil →</a>
            </x-slot:footer>
        @else
            <div class="empty-state">
                <p>Belum ada produksi.</p>
                <p class="empty-hint">Buat jadwal produksi untuk mulai menghitung biaya.</p>
                <a href="{{ route('production-orders.create') }}" class="btn-primary mt-5 inline-flex">+ Buat Produksi Pertama</a>
            </div>
        @endif
    </x-table-card>

    @if ($orders->hasPages())
        <div class="pagination-wrap mt-4">{{ $orders->links() }}</div>
    @endif
@endsection
