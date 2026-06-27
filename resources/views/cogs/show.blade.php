@extends('layouts.app')

@section('title', 'Detail COGS')
@section('heading', 'Detail Hasil COGS')
@section('subheading', $calculation->product->name)

@section('content')
    <x-step-header number="6" title="Detail Perhitungan"
        description="Rincian biaya perhitungan COGS." />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Total Biaya" :value="$format::rupiah($calculation->total_cogs)" color="brand" />
        <x-stat-card label="Bahan Baku" :value="$format::rupiah($calculation->direct_material)" color="green" />
        <x-stat-card label="Tenaga Kerja" :value="$format::rupiah($calculation->direct_labor)" color="amber" />
        <x-stat-card label="Biaya/Unit" :value="$format::rupiah($calculation->unit_cogs, 2)" color="slate" />
    </div>

    <div class="card mb-6">
        <dl class="grid gap-4 sm:grid-cols-2 text-sm">
            <div><dt class="text-slate-500">Produk</dt><dd class="font-medium">{{ $calculation->product->name }}</dd></div>
            <div><dt class="text-slate-500">Jumlah</dt><dd class="font-medium">{{ $format::number($calculation->quantity, 0) }} {{ $calculation->product->unit }}</dd></div>
            <div><dt class="text-slate-500">Overhead</dt><dd class="font-medium">{{ $format::rupiah($calculation->manufacturing_overhead) }}</dd></div>
            <div><dt class="text-slate-500">Tanggal</dt><dd class="font-medium">{{ $calculation->calculated_at->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="text-slate-500">Metode</dt><dd class="font-medium">{{ $calculation->calculation_method }}</dd></div>
        </dl>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('cogs.history') }}" class="btn-secondary">← Kembali ke Daftar</a>
        <a href="{{ route('cogs.calculate') }}" class="btn-primary">+ Hitung COGS Baru</a>
        <form action="{{ route('cogs.history.destroy', $calculation) }}" method="POST" onsubmit="return confirm('Hapus riwayat ini?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn-danger">Hapus</button>
        </form>
    </div>
@endsection
