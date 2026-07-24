@extends('layouts.app')

@section('title', 'Beranda')
@section('heading')
    Beranda
@endsection
@section('subheading')
    Ringkasan penjualan, modal, dan data usaha
@endsection

@section('content')
    <section class="mb-10">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-2">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Penjualan {{ $today['label'] }}</h2>
                <p class="mt-1 text-sm text-slate-500">Omzet kasir, modal terjual, dan laba kotor.</p>
            </div>
        </div>

        <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Omzet" :value="$format::rupiah($today['omzet'])" color="green" />
            <x-stat-card label="Transaksi" :value="$format::number($today['count'], 0)" color="brand" />
            <x-stat-card label="Modal Terjual" :value="$format::rupiah($today['modal'])" color="slate" />
            <x-stat-card
                label="Laba Kotor"
                :value="$format::rupiah($today['laba']).($today['omzet'] > 0 ? ' ('.$format::number($today['margin'], 1).'%)' : '')"
                color="amber"
            />
        </div>

        @if ($today['count'] === 0)
            <div class="mb-4 card border-slate-200 bg-slate-50/60">
                <p class="text-sm font-medium text-slate-800">Belum ada penjualan hari ini</p>
                <p class="mt-1 text-sm text-slate-500">Data muncul otomatis setelah transaksi lunas di Kasir.</p>
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="card">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Rata-rata per transaksi</p>
                <p class="mt-2 text-xl font-bold text-slate-900">{{ $format::rupiah($today['average']) }}</p>
            </div>
            <div class="card">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Omzet {{ $month['label'] }}</p>
                <p class="mt-2 text-xl font-bold text-slate-900">{{ $format::rupiah($month['omzet']) }}</p>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $format::number($month['count'], 0) }} transaksi
                    · laba {{ $format::rupiah($month['laba']) }}
                    @if ($month['omzet'] > 0)
                        ({{ $format::number($month['margin'], 1) }}%)
                    @endif
                </p>
            </div>
        </div>
    </section>

    <section class="mb-10">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Data Usaha</h2>
            <p class="mt-1 text-sm text-slate-500">Ringkas jumlah menu dan bahan yang sudah dicatat.</p>
        </div>

        <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Menu Aktif" :value="$format::number($snapshot['menu_aktif'], 0)" color="brand" />
            <x-stat-card label="Bahan Baku" :value="$format::number($snapshot['bahan_baku'], 0)" color="green" />
            <x-stat-card label="Bahan Jadi" :value="$format::number($snapshot['bahan_jadi'], 0)" color="amber" />
            <x-stat-card label="Tanpa Harga / HPP" :value="$format::number($snapshot['menu_tanpa_harga'] + $snapshot['menu_tanpa_hpp'], 0)" color="rose" />
        </div>

        @if ($snapshot['menu_tanpa_harga'] > 0 || $snapshot['menu_tanpa_hpp'] > 0)
            <div class="card border-amber-200 bg-amber-50/40">
                <p class="text-sm font-medium text-slate-800">Perlu dilengkapi</p>
                <ul class="mt-2 space-y-1 text-sm text-slate-600">
                    @if ($snapshot['menu_tanpa_harga'] > 0)
                        <li>
                            {{ $format::number($snapshot['menu_tanpa_harga'], 0) }} menu belum punya harga jual —
                            <a href="{{ route('menu-pricing.index') }}" class="font-medium text-brand-700 hover:underline">atur di Harga Jual</a>
                        </li>
                    @endif
                    @if ($snapshot['menu_tanpa_hpp'] > 0)
                        <li>
                            {{ $format::number($snapshot['menu_tanpa_hpp'], 0) }} menu belum punya modal/HPP —
                            <a href="{{ route('products.index') }}" class="font-medium text-brand-700 hover:underline">lengkapi resep</a>
                        </li>
                    @endif
                </ul>
            </div>
        @endif
    </section>

    @if ($topMenus->isNotEmpty())
        <section class="mb-10">
            <x-table-card title="Menu Terlaris {{ $month['label'] }}">
                <table class="table-default">
                    <thead>
                        <tr>
                            <th>Menu</th>
                            <th>Terjual</th>
                            <th>Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($topMenus as $row)
                            <tr>
                                <td class="font-semibold text-slate-900">{{ $row['name'] }}</td>
                                <td>{{ $format::number($row['quantity'], 0) }}</td>
                                <td class="cell-money">{{ $format::rupiah($row['revenue']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-table-card>
        </section>
    @endif

    @if ($summary['total_records'] > 0)
        <section class="mb-10">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-slate-900">Ringkasan Modal</h2>
                <p class="mt-1 text-sm text-slate-500">Akumulasi perhitungan HPP dari resep, produksi, dan penjualan.</p>
            </div>

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
        </section>
    @endif

    <div class="card border-slate-200">
        <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div>
                <h3 class="font-semibold text-slate-800">Mulai dari Awal</h3>
                <p class="mt-1 text-sm text-slate-500">Hapus semua data dan ulang dari langkah 1.</p>
            </div>
            <a href="{{ route('reset-data.show') }}" class="btn-danger w-full sm:w-auto">Hapus Semua Data</a>
        </div>
    </div>
@endsection
