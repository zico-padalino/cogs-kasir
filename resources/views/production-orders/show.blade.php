@extends('layouts.app')

@section('title', $order->order_number)
@section('heading', 'Detail Produksi')
@section('subheading', $order->product->name . ' — ' . number_format($order->quantity_planned, 0) . ' ' . $order->product->unit)

@section('content')
    @php
        $statusInfo = match($order->status->value) {
            'draft' => ['Draft', 'Siap dimulai', 'bg-slate-100 text-slate-700'],
            'in_progress' => ['Berjalan', 'Sedang produksi', 'bg-blue-100 text-blue-700'],
            'completed' => ['Selesai', 'COGS sudah dihitung', 'bg-green-100 text-green-700'],
            default => ['?', '', ''],
        };
    @endphp

    <div class="card mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <span class="rounded-full px-3 py-1 text-sm font-medium {{ $statusInfo[2] }}">{{ $statusInfo[0] }}</span>
                <p class="mt-2 text-sm text-slate-500">{{ $statusInfo[1] }}</p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                @if ($order->status->value === 'draft')
                    <form action="{{ route('production-orders.start', $order) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-secondary btn-sm">Mulai Produksi</button>
                    </form>
                @endif

                @if (in_array($order->status->value, ['draft', 'in_progress']))
                    <form action="{{ route('production-orders.complete', $order) }}" method="POST">
                        @csrf
                        <button type="submit" class="btn-primary btn-sm" onclick="return confirm('Selesaikan dan hitung COGS?')">
                            Selesaikan & Hitung COGS
                        </button>
                    </form>
                @endif

                @if ($cogs)
                    <a href="{{ route('cogs.history.show', $cogs) }}" class="btn-outline btn-sm">Detail COGS</a>
                    <a href="{{ route('cogs.history') }}" class="btn-primary btn-sm">Lihat Hasil COGS →</a>
                @endif

                @if ($order->status->value === 'draft')
                    <a href="{{ route('production-orders.edit', $order) }}" class="btn-outline btn-sm">Edit</a>
                    <form action="{{ route('production-orders.destroy', $order) }}" method="POST" onsubmit="return confirm('Hapus order ini?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-outline-danger btn-sm">Hapus</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @if ($cogs)
        <x-step-header number="6" title="Hasil Perhitungan COGS"
            description="Total biaya untuk {{ number_format($order->quantity_completed, 0) }} unit {{ $order->product->name }}." />

        <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Total Biaya" :value="$format::rupiah($cogs->total_cogs)" color="brand" />
            <x-stat-card label="Bahan Baku" :value="$format::rupiah($cogs->direct_material)" color="green" />
            <x-stat-card label="Tenaga Kerja" :value="$format::rupiah($cogs->direct_labor)" color="amber" />
            <x-stat-card label="Biaya/Unit" :value="$format::rupiah($cogs->unit_cogs, 2)" color="slate" />
        </div>

        <div class="mb-6 rounded-xl border-2 border-brand-200 bg-brand-50 p-6 text-center">
            <p class="text-sm text-slate-600">Biaya per 1 {{ $order->product->unit }} {{ $order->product->name }}</p>
            <p class="mt-2 text-4xl font-bold text-brand-700">{{ $format::rupiah($cogs->unit_cogs, 2) }}</p>
            <p class="mt-2 text-xs text-slate-500">= Bahan + Tenaga Kerja + Overhead</p>
        </div>
    @else
        <div class="mb-6 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Belum ada hasil COGS. Klik <strong>Selesaikan & Hitung COGS</strong> setelah produksi selesai.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <h2 class="mb-4 font-semibold">Bahan yang Dipakai</h2>
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
                            <td>{{ $format::number($material->quantity_used ?: $material->quantity_planned, 2) }}</td>
                            <td class="font-medium">{{ $format::rupiah($material->total_cost) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2 class="mb-4 font-semibold">Tenaga Kerja</h2>
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
                                <td>{{ $format::number($labor->labor_hours, 1) }}</td>
                                <td>{{ $format::rupiah($labor->total_cost) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-sm text-slate-500">Tidak ada data tenaga kerja.</p>
            @endif
        </div>
    </div>

    <div class="mt-6">
        <a href="{{ route('production-orders.index') }}" class="text-sm text-brand-600">← Kembali</a>
    </div>
@endsection
