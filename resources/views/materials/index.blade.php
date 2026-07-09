@extends('layouts.app')

@section('title', 'Bahan')
@section('heading', 'Langkah 2: Bahan')
@section('subheading', 'Bahan baku + stok + harga beli — isi sekali langsung jadi')

@section('content')
    <div class="module-page module-step-2">
        <x-module-form-card :step="2" title="Tambah Bahan Baru" description="Nama bahan, stok awal, dan harga beli — cukup sekali isi.">
            <form action="{{ route('materials.store') }}" method="POST" class="overhead-add-form">
                @csrf
                <div class="field-name">
                    <label class="form-label">Nama bahan</label>
                    <input type="text" name="name" class="form-input" required placeholder="Tepung terigu" value="{{ old('name') }}">
                </div>
                <div class="field-base">
                    <label class="form-label">Satuan</label>
                    <input type="text" name="unit" class="form-input" required placeholder="kg" value="{{ old('unit', 'kg') }}">
                </div>
                <div class="field-rate">
                    <label class="form-label">Stok masuk</label>
                    <input type="number" name="quantity" class="form-input" step="0.01" min="0.01" required placeholder="25" value="{{ old('quantity') }}">
                </div>
                <div class="field-note">
                    <label class="form-label">Harga beli / satuan</label>
                    <x-rupiah-input name="unit_cost" placeholder="12.000" required />
                </div>
                <div class="field-submit">
                    <button type="submit" class="btn-primary w-full py-3 text-base font-semibold lg:px-3">Simpan Bahan</button>
                </div>
            </form>
        </x-module-form-card>

        <x-table-card :step="2" title="Daftar Bahan" :subtitle="$materials->count() . ' bahan terdaftar'">
            @if ($materials->isNotEmpty())
                <div class="space-y-3 p-4 sm:p-5">
                    @foreach ($materials as $material)
                        <div class="module-item-card">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-base font-bold text-slate-900">{{ $material->name }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span class="module-stat-pill module-stat-pill--stock">
                                            Stok {{ $format::number($material->available_qty, 2) }} {{ $material->unit }}
                                        </span>
                                        @if ($material->avg_cost > 0)
                                            <span class="module-stat-pill module-stat-pill--price">
                                                {{ $format::rupiah($material->avg_cost) }}/{{ $material->unit }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <details class="w-full sm:w-auto">
                                    <summary class="btn-primary btn-sm cursor-pointer list-none">+ Tambah stok</summary>
                                    <form action="{{ route('materials.receive') }}" method="POST" class="mt-3 space-y-2 rounded-xl border-2 border-emerald-100 bg-emerald-50/50 p-3">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $material->id }}">
                                        <div class="grid gap-2 sm:grid-cols-3">
                                            <input type="number" name="quantity" class="form-input text-sm" step="0.01" min="0.01" required placeholder="Jumlah">
                                            <x-rupiah-input name="unit_cost" placeholder="Harga/satuan" class="text-sm" required />
                                            <input type="text" name="lot_number" class="form-input text-sm" placeholder="No. batch (opsional)">
                                        </div>
                                        <button type="submit" class="btn-primary btn-sm w-full sm:w-auto">Simpan stok</button>
                                    </form>
                                </details>
                            </div>

                            @if ($material->inventoryLots->where('quantity_remaining', '>', 0)->isNotEmpty())
                                <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-slate-50/50">
                                    <table class="table-default table-compact">
                                        <thead>
                                            <tr>
                                                <th>Batch</th>
                                                <th>Sisa</th>
                                                <th>Harga</th>
                                                <th class="col-actions">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($material->inventoryLots->where('quantity_remaining', '>', 0) as $lot)
                                                <tr>
                                                    <td class="font-mono text-xs cell-muted">{{ $lot->lot_number ?? '-' }}</td>
                                                    <td>{{ $format::number($lot->quantity_remaining, 2) }} {{ $material->unit }}</td>
                                                    <td class="cell-money">{{ $format::rupiah($lot->unit_cost) }}</td>
                                                    <td class="col-actions">
                                                        <details class="inline-edit text-left">
                                                            <summary class="text-xs">Edit</summary>
                                                            <form action="{{ route('materials.lots.update', $lot) }}" method="POST" class="inline-edit-panel">
                                                                @csrf @method('PUT')
                                                                <input type="text" name="lot_number" class="form-input text-xs" value="{{ $lot->lot_number }}">
                                                                <input type="number" name="quantity_remaining" class="form-input text-xs" step="0.01" min="0" max="{{ $lot->quantity_received }}" value="{{ $lot->quantity_remaining }}">
                                                                <x-rupiah-input name="unit_cost" :value="$lot->unit_cost" class="text-xs" />
                                                                <button type="submit" class="btn-primary btn-sm w-full">Simpan</button>
                                                            </form>
                                                            @if ((float) $lot->quantity_remaining >= (float) $lot->quantity_received)
                                                                <form action="{{ route('materials.lots.destroy', $lot) }}" method="POST" class="mt-2" onsubmit="return confirm('Hapus batch ini?')">
                                                                    @csrf @method('DELETE')
                                                                    <button type="submit" class="btn-outline-danger btn-sm w-full">Hapus</button>
                                                                </form>
                                                            @endif
                                                        </details>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <x-slot:footer>
                    <p class="text-sm font-medium text-slate-600">Bahan sudah cukup? Lanjut daftarkan menu.</p>
                    <a href="{{ route('products.index') }}" class="btn-primary btn-sm">Lanjut ke Menu →</a>
                </x-slot:footer>
            @else
                <div class="module-empty">
                    <span class="module-empty__icon" aria-hidden="true">🥬</span>
                    <p class="module-empty__title">Belum ada bahan</p>
                    <p class="module-empty__hint">Isi form di atas — contoh: Tepung, 25 kg, Rp 12.000/kg.</p>
                </div>
            @endif
        </x-table-card>
    </div>
@endsection
