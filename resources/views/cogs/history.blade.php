@extends('layouts.app')

@section('title', 'Hasil Perhitungan')
@section('heading', 'Langkah 6: Hasil Perhitungan')
@section('subheading', 'Riwayat semua perhitungan biaya produksi')

@section('content')
    <x-step-header number="6" title="Hasil Perhitungan Biaya"
        description="Lihat riwayat biaya yang sudah terhitung. Bisa dihitung ulang manual atau dari produksi." />

    <div class="page-toolbar">
        <p class="text-sm text-slate-500 sm:flex-1">Semua hasil perhitungan biaya pokok produk.</p>
        <a href="{{ route('cogs.calculate') }}" class="btn-primary shrink-0">+ Hitung Manual</a>
    </div>

    <x-table-card title="Riwayat Perhitungan" subtitle="{{ $calculations->total() }} catatan">
        @if ($calculations->isNotEmpty())
            <table class="table-default">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Produk</th>
                        <th>Jumlah</th>
                        <th>Bahan</th>
                        <th>Gaji</th>
                        <th>Ops</th>
                        <th>Total</th>
                        <th>Per Unit</th>
                        <th class="col-actions">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($calculations as $calc)
                        <tr>
                            <td class="whitespace-nowrap text-xs cell-muted">{{ $calc->calculated_at->format('d/m/Y H:i') }}</td>
                            <td class="font-semibold text-slate-900">{{ $calc->product->name }}</td>
                            <td>{{ $format::number($calc->quantity, 0) }}</td>
                            <td class="cell-money">{{ $format::rupiah($calc->direct_material) }}</td>
                            <td class="cell-money">{{ $format::rupiah($calc->direct_labor) }}</td>
                            <td class="cell-money">{{ $format::rupiah($calc->manufacturing_overhead) }}</td>
                            <td class="cell-money font-semibold">{{ $format::rupiah($calc->total_cogs) }}</td>
                            <td class="cell-highlight">{{ $format::rupiah($calc->unit_cogs, 2) }}</td>
                            <td class="col-actions">
                                <x-crud-actions
                                    :show="route('cogs.history.show', $calc)"
                                    :delete="route('cogs.history.destroy', $calc)"
                                />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty-state">
                <p>Belum ada hasil.</p>
                <p class="empty-hint">Selesaikan produksi di Langkah 5 untuk melihat biaya di sini.</p>
                <a href="{{ route('production-orders.index') }}" class="btn-primary mt-5 inline-flex">Ke Produksi</a>
            </div>
        @endif
    </x-table-card>

    @if ($calculations->hasPages())
        <div class="pagination-wrap mt-4">{{ $calculations->links() }}</div>
    @endif

    <div class="info-box mt-6">
        <h3 class="font-semibold text-slate-800">Rumus singkat</h3>
        <p class="mt-2">
            <strong>Biaya pokok</strong> = Bahan + Gaji pekerja + Biaya operasional<br>
            <strong>Biaya per unit</strong> = Total biaya ÷ Jumlah produk
        </p>
    </div>
@endsection
