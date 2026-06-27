@extends('layouts.app')

@section('title', 'Hasil COGS')
@section('heading', 'Hasil Perhitungan COGS')
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
            <x-stat-card label="Total COGS" :value="$format::rupiah($calc->total_cogs)" color="brand" />
            <x-stat-card label="Pendapatan" :value="$format::rupiah($sale->total_revenue)" color="green" />
            <x-stat-card label="Laba Kotor" :value="$format::rupiah($grossProfit)" color="amber" />
            <x-stat-card label="Margin" :value="number_format($margin, 1) . '%'" color="rose" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card">
                <h2 class="mb-4 font-semibold">Rincian COGS</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Bahan Langsung</dt><dd class="font-medium">{{ $format::rupiah($calc->direct_material) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Tenaga Kerja</dt><dd class="font-medium">{{ $format::rupiah($calc->direct_labor) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Overhead</dt><dd class="font-medium">{{ $format::rupiah($calc->manufacturing_overhead) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-3"><dt class="font-semibold">COGS/Unit</dt><dd class="font-bold text-brand-600">{{ $format::rupiah($calc->unit_cogs, 2) }}</dd></div>
                </dl>
            </div>
            <div class="card">
                <h2 class="mb-4 font-semibold">Info Penjualan</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Invoice</dt><dd class="font-mono">{{ $sale->invoice_number }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Harga Jual/Unit</dt><dd>{{ $format::rupiah($sale->selling_price) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Metode</dt><dd>{{ $calc->calculation_method }}</dd></div>
                </dl>
            </div>
        </div>
    @else
        <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Total COGS" :value="$format::rupiah($cogsResult->totalCogs)" color="brand" />
            <x-stat-card label="Bahan Langsung" :value="$format::rupiah($cogsResult->directMaterial)" color="green" />
            <x-stat-card label="Overhead" :value="$format::rupiah($cogsResult->manufacturingOverhead)" color="rose" />
            <x-stat-card label="COGS/Unit" :value="$format::rupiah($cogsResult->unitCogs, 2)" color="slate" />
        </div>

        <div class="card">
            <h2 class="mb-4 font-semibold">BOM Cost Roll-up</h2>
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
