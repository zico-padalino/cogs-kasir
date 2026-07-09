@extends('layouts.app')

@section('title', 'Bahan')
@section('heading', 'Langkah 2: Bahan')
@section('subheading', 'Bahan baku + stok + harga beli — isi sekali langsung jadi')

@section('content')
    <div class="space-y-4">
        <div class="card overhead-add-card">
            <div class="mb-3">
                <h2 class="text-sm font-semibold text-slate-900">Tambah Bahan Baru</h2>
                <p class="mt-0.5 text-xs text-slate-500">Nama bahan, stok awal, dan harga beli diisi sekaligus.</p>
            </div>

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
                    <button type="submit" class="btn-primary w-full lg:px-3">Simpan</button>
                </div>
            </form>
        </div>

        <x-table-card class="cogs-history-card" title="Daftar Bahan" subtitle="{{ $materials->count() }} bahan terdaftar">
            @if ($materials->isNotEmpty())
                <div class="divide-y divide-slate-100">
                    @foreach ($materials as $material)
                        <div class="px-4 py-3">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">{{ $material->name }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500">
                                        Stok <strong class="text-slate-700">{{ $format::number($material->available_qty, 2) }} {{ $material->unit }}</strong>
                                        @if ($material->avg_cost > 0)
                                            · Rata-rata <strong class="text-slate-700">{{ $format::rupiah($material->avg_cost) }}/{{ $material->unit }}</strong>
                                        @endif
                                    </p>
                                </div>
                                <details class="w-full sm:w-auto">
                                    <summary class="btn-secondary btn-sm cursor-pointer list-none">+ Tambah stok</summary>
                                    <form action="{{ route('materials.receive') }}" method="POST" class="mt-2 space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
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
                                <div class="mt-2 overflow-x-auto rounded-lg border border-slate-100">
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
                    <p class="text-sm text-slate-500">Bahan sudah cukup? Lanjut daftarkan produk jadi.</p>
                    <a href="{{ route('products.index') }}" class="btn-primary btn-sm">Lanjut ke Menu →</a>
                </x-slot:footer>
            @else
                <div class="cogs-history-empty">
                    <p class="text-sm text-slate-600">Belum ada bahan.</p>
                    <p class="mt-1 text-xs text-slate-500">Isi form di atas untuk menambah bahan pertama.</p>
                </div>
            @endif
        </x-table-card>
    </div>
@endsection
