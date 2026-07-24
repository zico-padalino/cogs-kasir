@extends('layouts.app')

@section('title', 'Beranda')
@section('heading')
    Beranda
@endsection
@section('subheading')
    Kelola bahan, menu, dan harga jual
@endsection

@section('content')
    @if ($summary['total_records'] > 0)
        <div>
            <h2 class="mb-4 text-lg font-semibold">Ringkasan Modal</h2>
            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card label="Total Modal" :value="$format::rupiah($summary['total_cogs'])" color="brand" />
                <x-stat-card label="Bahan Baku" :value="$format::rupiah($summary['total_direct_material'])" color="green" />
                <x-stat-card label="Upah Kerja" :value="$format::rupiah($summary['total_direct_labor'])" color="amber" />
                <x-stat-card label="Biaya Lain" :value="$format::rupiah($summary['total_overhead'])" color="rose" />
            </div>

            @if (count($summary['by_product']) > 0)
                <x-table-card title="Modal per Menu">
                    <table class="table-default">
                        <thead>
                            <tr>
                                <th>Menu</th>
                                <th>Jumlah</th>
                                <th>Total Modal</th>
                                <th>Modal / Porsi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['by_product'] as $row)
                                <tr>
                                    <td class="font-semibold text-slate-900">{{ $row['name'] }}</td>
                                    <td>{{ $format::number($row['total_quantity'], 0) }}</td>
                                    <td class="cell-money">{{ $format::rupiah($row['total_cogs']) }}</td>
                                    <td class="cell-highlight">{{ $format::rupiah($row['average_unit_cogs']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-table-card>
            @endif
        </div>
    @endif

    <div class="mt-10 card border-slate-200">
        <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div>
                <h3 class="font-semibold text-slate-800">Mulai dari Awal</h3>
                <p class="mt-1 text-sm text-slate-500">Hapus semua data dan ulang dari langkah 1.</p>
            </div>
            <a href="{{ route('reset-data.show') }}" class="btn-danger w-full sm:w-auto">Hapus Semua Data</a>
        </div>
    </div>
@endsection
