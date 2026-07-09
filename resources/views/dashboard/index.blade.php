@extends('layouts.app')

@section('title', $progress['complete'] ? 'Beranda' : 'Panduan COGS')
@section('heading')
    {{ $progress['complete'] ? 'Beranda' : 'Panduan Perhitungan COGS' }}
@endsection
@section('subheading')
    @if ($progress['complete'])
        Kelola data dan pantau hasil perhitungan COGS
    @else
        Ikuti 6 langkah berikut — dari nol sampai dapat biaya produk
    @endif
@endsection

@section('content')
    @if ($progress['complete'])
        <div class="card mb-8 border-green-200 bg-gradient-to-r from-green-50 to-white">
            <h2 class="text-lg font-semibold text-slate-900">Setup selesai</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Semua langkah panduan sudah dijalankan. Panduan langkah demi langkah disembunyikan —
                gunakan menu di samping untuk mengelola data kapan saja.
            </p>
        </div>

        <div class="mb-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($steps as $step)
                <a href="{{ route($step['route']) }}" class="card flex items-center justify-between gap-3 transition hover:border-brand-200 hover:shadow-md">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $step['title'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $step['description'] }}</p>
                    </div>
                    <span class="text-brand-600">→</span>
                </a>
            @endforeach
        </div>
    @else
        {{-- Penjelasan singkat --}}
        <div class="card mb-8 border-brand-100 bg-gradient-to-r from-brand-50 to-white">
            <h2 class="text-lg font-semibold text-slate-900">HPP = COGS (satu perhitungan)</h2>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                <strong>HPP (Harga Pokok Penjualan)</strong> dihitung sekali dari bahan + tenaga kerja + overhead.
                <strong>COGS</strong> di laporan keuangan mengacu ke nilai HPP yang sama — bukan rumus terpisah.
            </p>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Produk jadi dari stok COGS ditandai sebagai <strong>menu kasir</strong> (<code>is_menu_item</code>),
                lalu di Kasir Anda atur <strong>harga jual</strong> per menu.
            </p>
            <ol class="mt-4 list-inside list-decimal space-y-1 text-sm text-slate-700">
                <li>Bahan baku → BOM → Produksi → dapat <strong>unit_hpp</strong></li>
                <li>Produk jadi aktif sebagai menu → atur <strong>selling_price</strong> di Kasir</li>
                <li>Penjualan POS → HPP/COGS tercatat otomatis per transaksi</li>
            </ol>
            <p class="mt-4 text-xs text-slate-500">Detail lengkap: <code>database/ALUR_HPP_COGS.md</code></p>
        </div>

        {{-- Progress --}}
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4">
            <div class="flex-1">
                <div class="flex justify-between text-sm">
                    <span class="font-medium text-slate-700">Progress</span>
                    <span class="text-brand-600">{{ $progress['percent'] }}%</span>
                </div>
                <div class="mt-2 h-3 overflow-hidden rounded-full bg-slate-200">
                    <div class="h-full rounded-full bg-brand-600 transition-all" style="width: {{ $progress['percent'] }}%"></div>
                </div>
            </div>
            <a href="{{ route($progress['current']['route']) }}" class="btn-primary w-full shrink-0 text-center sm:w-auto">Lanjut Langkah {{ $progress['current']['number'] }}</a>
        </div>

        {{-- 6 Langkah --}}
        <div class="space-y-4">
            @foreach ($steps as $step)
                <div class="card flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between {{ $step['done'] ? 'border-green-200 bg-green-50/30' : ($step['number'] === $progress['currentStep'] ? 'ring-2 ring-brand-300' : '') }}">
                    <div class="flex gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-lg font-bold {{ $step['done'] ? 'bg-green-600 text-white' : 'bg-slate-200 text-slate-600' }}">
                            @if ($step['done'])
                                ✓
                            @else
                                {{ $step['number'] }}
                            @endif
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-slate-900">{{ $step['title'] }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $step['description'] }}</p>
                            <p class="mt-2 text-xs text-slate-500">💡 {{ $step['hint'] }}</p>
                        </div>
                    </div>
                    <a href="{{ route($step['route']) }}" class="{{ $step['done'] ? 'btn-secondary' : 'btn-primary' }} w-full shrink-0 text-center sm:w-auto">
                        {{ $step['done'] ? 'Buka lagi' : 'Mulai' }}
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Hasil jika sudah ada COGS --}}
    @if ($summary['total_records'] > 0)
        <div class="mt-10">
            <h2 class="mb-4 text-lg font-semibold">Ringkasan Hasil</h2>
            <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card label="Total COGS" :value="$format::rupiah($summary['total_cogs'])" color="brand" />
                <x-stat-card label="Bahan Baku" :value="$format::rupiah($summary['total_direct_material'])" color="green" />
                <x-stat-card label="Tenaga Kerja" :value="$format::rupiah($summary['total_direct_labor'])" color="amber" />
                <x-stat-card label="Overhead" :value="$format::rupiah($summary['total_overhead'])" color="rose" />
            </div>

            @if (count($summary['by_product']) > 0)
                <x-table-card title="Biaya per Produk">
                    <table class="table-default">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th>Jumlah</th>
                                <th>Total Biaya</th>
                                <th>Biaya/Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['by_product'] as $row)
                                <tr>
                                    <td class="font-semibold text-slate-900">{{ $row['name'] }}</td>
                                    <td>{{ $format::number($row['total_quantity'], 0) }}</td>
                                    <td class="cell-money">{{ $format::rupiah($row['total_cogs']) }}</td>
                                    <td class="cell-highlight">{{ $format::rupiah($row['average_unit_cogs'], 2) }}</td>
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
                <h3 class="font-semibold text-slate-800">Reset Data</h3>
                <p class="mt-1 text-sm text-slate-500">Hapus semua data dan mulai ulang dari langkah 1.</p>
            </div>
            <a href="{{ route('reset-data.show') }}" class="btn-danger w-full sm:w-auto">Reset Semua Data</a>
        </div>
    </div>
@endsection
