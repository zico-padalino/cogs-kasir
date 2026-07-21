@extends('layouts.app')

@section('title', 'Hasil Perhitungan')
@section('heading', 'Hasil Perhitungan Biaya')
@section('subheading', $product->name . ' × ' . number_format($quantity, 0) . ' ' . $product->unit)

@section('content')
    @if ($isSale)
        @php
            $calc = $result['calculation'];
            $sale = $result['sale'];
            $grossProfit = $result['grossProfit'];
            $margin = $sale->total_revenue > 0 ? ($grossProfit / $sale->total_revenue) * 100 : 0;
        @endphp

        <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Total Biaya Pokok" :value="$format::rupiah($calc->totalHpp())" color="brand" />
            <x-stat-card label="Pendapatan" :value="$format::rupiah($sale->total_revenue)" color="green" />
            <x-stat-card label="Laba Kotor" :value="$format::rupiah($grossProfit)" color="amber" />
            <x-stat-card label="Persentase Laba" :value="$format::number($margin) . '%'" color="rose" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <h2 class="mb-4 font-semibold">Rincian biaya</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Bahan Baku</dt><dd class="font-medium">{{ $format::rupiah($calc->direct_material) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Gaji pekerja</dt><dd class="font-medium">{{ $format::rupiah($calc->direct_labor) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Biaya tambahan</dt><dd class="font-medium">{{ $format::rupiah($calc->manufacturing_overhead) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-3"><dt class="font-semibold">Biaya per unit</dt><dd class="font-bold text-brand-600">{{ $format::rupiah($calc->unitHpp()) }}</dd></div>
                </dl>
            </div>
            <div class="card">
                <h2 class="mb-4 font-semibold">Info penjualan</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">No. nota</dt><dd class="font-mono">{{ $sale->invoice_number }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Harga jual per unit</dt><dd>{{ $format::rupiah($sale->selling_price) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Cara hitung</dt><dd>{{ $calc->calculation_method }}</dd></div>
                </dl>
            </div>
        </div>
    @else
        <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Total Biaya Pokok" :value="$format::rupiah($cogsResult->totalHpp)" color="brand" />
            <x-stat-card label="Bahan Baku" :value="$format::rupiah($cogsResult->directMaterial)" color="green" />
            <x-stat-card label="Biaya Tambahan" :value="$format::rupiah($cogsResult->manufacturingOverhead)" color="rose" />
            <x-stat-card label="Biaya per Unit" :value="$format::rupiah($cogsResult->unitHpp)" color="slate" />
        </div>

        <div class="card">
            <h2 class="mb-4 font-semibold">Rincian biaya per bahan</h2>
            @if (!empty($cogsResult->breakdown['bom_roll_up']))
                @include('cogs._bom-tree', ['node' => $cogsResult->breakdown['bom_roll_up'], 'format' => $format, 'depth' => 0])
            @endif
        </div>
    @endif

    <div class="form-actions mt-6">
        <a href="{{ route('cogs.calculate') }}" class="btn-primary">Hitung Lagi</a>
        <a href="{{ route('cogs.history') }}" class="btn-secondary">Lihat Riwayat</a>
    </div>
@endsection
