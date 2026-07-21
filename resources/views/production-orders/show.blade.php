@extends('layouts.app')

@section('title', $order->order_number)
@section('heading', 'Detail Produksi')
@section('subheading', $order->product->name . ' — ' . number_format($order->quantity_planned, 0) . ' ' . $order->product->unit)

@section('content')
    @php
        $statusInfo = match($order->status->value) {
            'draft' => ['Belum dimulai', 'Siap dikerjakan', 'bg-slate-100 text-slate-700'],
            'in_progress' => ['Sedang jalan', 'Produksi berlangsung', 'bg-blue-100 text-blue-700'],
            'completed' => ['Selesai', 'Biaya sudah terhitung', 'bg-green-100 text-green-700'],
            default => ['?', '', ''],
        };
    @endphp

    <div class="card mb-6">
        <div class="flex flex-col gap-4">
            <div>
                <span class="rounded-full px-3 py-1 text-sm font-medium {{ $statusInfo[2] }}">{{ $statusInfo[0] }}</span>
                <p class="mt-2 text-sm text-slate-500">{{ $statusInfo[1] }}</p>
            </div>

            <div class="page-actions-group">
                @if ($order->status->value === 'draft')
                    <form action="{{ route('production-orders.start', $order) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-secondary btn-sm">Mulai Produksi</button>
                    </form>
                @endif

                @if (in_array($order->status->value, ['draft', 'in_progress']))
                    <form action="{{ route('production-orders.complete', $order) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-primary btn-sm" onclick="return confirm('Tandai selesai dan hitung biaya?')">
                            Selesai & Hitung Biaya
                        </button>
                    </form>
                @endif

                @if ($cogs)
                    <a href="{{ route('cogs.history.show', $cogs) }}" class="btn-outline btn-sm">Lihat Rincian Biaya</a>
                    <a href="{{ route('cogs.history') }}" class="btn-primary btn-sm">Ke Hasil Perhitungan →</a>
                @endif

                @if ($order->status->value === 'draft')
                    <a href="{{ route('production-orders.edit', $order) }}" class="btn-outline btn-sm">Edit</a>
                    <form action="{{ route('production-orders.destroy', $order) }}" method="POST" onsubmit="return confirm('Hapus jadwal ini?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-outline-danger btn-sm">Hapus</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @if ($cogs)
        <x-step-header number="5" title="Hasil Perhitungan Biaya"
            description="Total biaya untuk {{ number_format($order->quantity_completed, 0) }} {{ $order->product->unit }} {{ $order->product->name }}." />

        <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Total Biaya" :value="$format::rupiah($cogs->total_cogs)" color="brand" />
            <x-stat-card label="Bahan Baku" :value="$format::rupiah($cogs->direct_material)" color="green" />
            <x-stat-card label="Gaji Pekerja" :value="$format::rupiah($cogs->direct_labor)" color="amber" />
            <x-stat-card label="Biaya per Unit" :value="$format::rupiah($cogs->unit_cogs)" color="slate" />
        </div>

        <div class="hero-stat mb-6">
            <p class="text-sm text-slate-600">Biaya per 1 {{ $order->product->unit }} {{ $order->product->name }}</p>
            <p class="hero-stat-value">{{ $format::rupiah($cogs->unit_cogs) }}</p>
            <p class="mt-2 text-xs text-slate-500">= Bahan Baku + Upah kerja + Biaya tambahan</p>
        </div>
    @else
        <div class="mb-6 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Belum ada hasil biaya. Klik <strong>Selesai & Hitung Biaya</strong> setelah produksi selesai.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <h2 class="mb-4 font-semibold">Bahan yang dipakai</h2>
            <table class="table-default">
                <thead>
                    <tr>
                        <th>Bahan</th>
                        <th>Dipakai</th>
                        <th>Biaya</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order->materials as $material)
                        <tr>
                            <td>{{ $material->product->name }}</td>
                            <td>{{ $format::number($material->quantity_used ?: $material->quantity_planned) }}</td>
                            <td class="font-medium">{{ $format::rupiah($material->total_cost) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2 class="mb-4 font-semibold">Gaji pekerja</h2>
            @if ($order->labors->isNotEmpty())
                <table class="table-default">
                    <thead>
                        <tr>
                            <th>Pekerjaan</th>
                            <th>Jam</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($order->labors as $labor)
                            <tr>
                                <td>{{ $labor->description }}</td>
                                <td>{{ $format::number($labor->labor_hours) }}</td>
                                <td>{{ $format::rupiah($labor->total_cost) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-sm text-slate-500">Tidak ada data gaji pekerja.</p>
            @endif
        </div>
    </div>

    <x-page-actions :back="route('production-orders.index')" back-label="← Daftar produksi" />
@endsection
