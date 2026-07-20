@extends('layouts.app')

@section('title', 'Inventaris Operasional')
@section('heading', 'Inventaris Operasional')
@section('subheading', 'Gelas, piring, dan benda operasional — catat stok & kerusakan')

@section('content')
    <div class="module-page">
        @if (session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('error') }}</div>
        @endif

        <div class="card mb-4">
            <h2 class="mb-3 font-display text-base font-semibold text-espresso">Tambah item</h2>
            <form action="{{ route('ops-assets.store') }}" method="POST" class="grid gap-3 sm:grid-cols-2">
                @csrf
                <div>
                    <label class="form-label">Nama</label>
                    <input type="text" name="name" class="form-input" required placeholder="Gelas latte" value="{{ old('name') }}">
                </div>
                <div>
                    <label class="form-label">Satuan</label>
                    <input type="text" name="unit" class="form-input" value="{{ old('unit', 'pcs') }}" placeholder="pcs">
                </div>
                <div>
                    <label class="form-label">Stok awal</label>
                    <input type="number" step="any" min="0" name="quantity" class="form-input" value="{{ old('quantity', 0) }}">
                </div>
                <div>
                    <label class="form-label">Catatan</label>
                    <input type="text" name="note" class="form-input" value="{{ old('note') }}" placeholder="Opsional">
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="btn-primary">Simpan item</button>
                </div>
            </form>
        </div>

        <div class="table-card mb-4">
            <div class="table-card-header">
                <div>
                    <h2 class="font-display text-base font-semibold text-espresso">Daftar item</h2>
                    <p class="text-xs text-slate-500">{{ $assets->count() }} item aktif</p>
                </div>
            </div>
            <div class="space-y-3 p-4">
                @forelse ($assets as $asset)
                    <div class="rounded-xl border border-brand-100 bg-white p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-espresso">{{ $asset->name }}</p>
                                <p class="text-xs text-slate-500">Sisa: <strong>{{ number_format((float) $asset->quantity_on_hand, 0) }} {{ $asset->unit }}</strong></p>
                            </div>
                        </div>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <form action="{{ route('ops-assets.receive', $asset) }}" method="POST" class="flex gap-2">
                                @csrf
                                <input type="number" step="any" min="0.000001" name="quantity" class="form-input" placeholder="Tambah" required>
                                <button type="submit" class="btn-secondary btn-sm shrink-0">+ Stok</button>
                            </form>
                            <form action="{{ route('ops-assets.damage', $asset) }}" method="POST" class="flex gap-2">
                                @csrf
                                <input type="number" step="any" min="0.000001" name="quantity" class="form-input" placeholder="Rusak" required>
                                <input type="text" name="note" class="form-input" placeholder="Catatan">
                                <button type="submit" class="btn-outline-danger btn-sm shrink-0">Rusak</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-500">Belum ada item. Tambah gelas/piring di atas.</p>
                @endforelse
            </div>
        </div>

        <div class="table-card">
            <div class="table-card-header">
                <h2 class="font-display text-base font-semibold text-espresso">Riwayat</h2>
            </div>
            <div class="table-scroll">
                <table class="table-default">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Item</th>
                            <th>Aksi</th>
                            <th>Qty</th>
                            <th>Sisa</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap text-xs text-slate-500">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                                <td>{{ $log->asset?->name ?? '—' }}</td>
                                <td>{{ $log->actionLabel() }}</td>
                                <td>{{ number_format((float) $log->quantity, 0) }}</td>
                                <td>{{ number_format((float) $log->quantity_after, 0) }}</td>
                                <td class="text-xs text-slate-500">{{ $log->note ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-sm text-slate-500">Belum ada riwayat.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
