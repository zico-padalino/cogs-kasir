@extends('layouts.app')

@section('title', 'Bahan')
@section('heading', 'Langkah 2: Bahan')
@section('subheading', 'Bahan baku + stok + harga beli — isi sekali langsung jadi')

@section('content')
    <div class="module-page module-step-2">
        <div class="page-toolbar">
            <p class="text-sm text-slate-500">Kelola bahan, stok sisa, dan stok masuk</p>
            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ route('materials.pdf', ['autoprint' => 1]) }}"
                    target="_blank"
                    rel="noopener"
                    class="btn-outline btn-sm shrink-0"
                >
                    PDF Sisa Bahan
                </a>
                <button
                    type="button"
                    class="btn-outline btn-sm shrink-0"
                    data-material-history-open
                >
                    Riwayat
                </button>
            </div>
        </div>

        <x-module-form-card :step="2" title="Tambah Bahan Baru" description="Pilih satuan stok untuk resep, lalu isi pembelian (langsung, dus, atau konversi kg→porsi).">
            <form action="{{ route('materials.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="form-label">Nama bahan</label>
                    <input type="text" name="name" class="form-input text-base" required placeholder="Keju slice" value="{{ old('name') }}">
                </div>

                <x-unit-picker
                    :selected="old('unit_preset', 'pcs')"
                    :custom-value="old('unit_custom', '')"
                />
                <p class=" -mt-2 text-xs text-slate-500">
                    Untuk keju/daging yang dihitung per porsi, satuan stok biasanya <strong>pcs / buah</strong>, lalu di cara beli pilih <strong>Konversi kg/liter</strong>.
                </p>

                <x-material-purchase-fields />

                <button type="submit" class="btn-primary w-full py-3 text-base font-semibold">Simpan Bahan</button>
            </form>
        </x-module-form-card>

        <x-table-card :step="2" title="Daftar Bahan" :subtitle="$materials->count() . ' bahan terdaftar'">
            @if ($materials->isNotEmpty())
                <div class="space-y-3 p-4 sm:p-5">
                    @foreach ($materials as $material)
                        <div class="module-item-card">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-base font-bold text-slate-900">{{ $material->name }}</p>
                                        <details class="inline-block">
                                            <summary class="cursor-pointer text-xs font-semibold text-brand-600 hover:text-brand-700">Ubah nama</summary>
                                            <form
                                                action="{{ route('materials.update', $material) }}"
                                                method="POST"
                                                class="mt-2 flex flex-wrap items-end gap-2 rounded-xl border border-brand-100 bg-brand-50/50 p-2"
                                            >
                                                @csrf
                                                @method('PUT')
                                                <div class="min-w-[12rem] flex-1">
                                                    <label class="form-label text-xs" for="material-name-{{ $material->id }}">Nama bahan</label>
                                                    <input
                                                        id="material-name-{{ $material->id }}"
                                                        type="text"
                                                        name="name"
                                                        class="form-input text-sm"
                                                        required
                                                        maxlength="255"
                                                        value="{{ old('name', $material->name) }}"
                                                    >
                                                </div>
                                                <button type="submit" class="btn-primary btn-sm">Simpan</button>
                                            </form>
                                        </details>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span class="module-stat-pill module-stat-pill--stock">
                                            Stok sisa {{ $format::number($material->available_qty, 2) }} {{ $material->unit }}
                                        </span>
                                        @if ($material->avg_cost > 0)
                                            <span class="module-stat-pill module-stat-pill--price">
                                                {{ $format::rupiah($material->avg_cost) }}/{{ $material->unit }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-start">
                                    <details class="w-full sm:w-auto">
                                        <summary class="btn-outline btn-sm cursor-pointer list-none text-center">Ubah stok sisa</summary>
                                        <form
                                            action="{{ route('materials.stock.adjust', $material) }}"
                                            method="POST"
                                            class="mt-3 space-y-3 rounded-xl border-2 border-amber-100 bg-amber-50/60 p-3"
                                        >
                                            @csrf
                                            @method('PUT')
                                            <p class="text-xs text-slate-600">
                                                Stok sistem sekarang:
                                                <strong>{{ $format::number($material->available_qty, 2) }} {{ $material->unit }}</strong>
                                            </p>
                                            <x-stock-remaining-fields
                                                :stock-unit="$material->unit"
                                                :current-qty="$material->available_qty"
                                            />
                                            <button type="submit" class="btn-primary btn-sm w-full">Simpan stok sisa</button>
                                        </form>
                                    </details>

                                    <details class="w-full sm:w-auto">
                                        <summary class="btn-primary btn-sm cursor-pointer list-none text-center">+ Tambah stok</summary>
                                        <form action="{{ route('materials.receive') }}" method="POST" class="mt-3 space-y-3 rounded-xl border-2 border-emerald-100 bg-emerald-50/50 p-3">
                                            @csrf
                                            <input type="hidden" name="product_id" value="{{ $material->id }}">
                                            <p class="text-xs text-slate-600">
                                                Satuan stok: <strong>{{ $material->unit }}</strong>
                                            </p>
                                            <x-material-purchase-fields :compact="true" :stock-unit-label="$material->unit" />
                                            <div>
                                                <label class="form-label text-xs">No. batch</label>
                                                <input type="text" name="lot_number" class="form-input text-sm" placeholder="Opsional">
                                            </div>
                                            <button type="submit" class="btn-primary btn-sm w-full sm:w-auto">Simpan stok masuk</button>
                                        </form>
                                    </details>
                                </div>
                            </div>

                            @if ($material->inventoryLots->where('quantity_remaining', '>', 0)->isNotEmpty())
                                <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-slate-50/50">
                                    <table class="table-default table-compact">
                                        <thead>
                                            <tr>
                                                <th>Batch</th>
                                                <th>Masuk</th>
                                                <th>Sisa</th>
                                                <th>Harga</th>
                                                <th class="col-actions">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($material->inventoryLots->where('quantity_remaining', '>', 0) as $lot)
                                                <tr>
                                                    <td class="font-mono text-xs cell-muted">{{ $lot->lot_number ?? '-' }}</td>
                                                    <td>{{ $format::number($lot->quantity_received, 2) }} {{ $material->unit }}</td>
                                                    <td class="font-semibold text-slate-800">{{ $format::number($lot->quantity_remaining, 2) }} {{ $material->unit }}</td>
                                                    <td class="cell-money">{{ $format::rupiah($lot->unit_cost) }}</td>
                                                    <td class="col-actions">
                                                        <details class="inline-edit text-left">
                                                            <summary class="btn-outline btn-sm cursor-pointer list-none">Edit batch</summary>
                                                            <form action="{{ route('materials.lots.update', $lot) }}" method="POST" class="inline-edit-panel space-y-2">
                                                                @csrf @method('PUT')
                                                                <div>
                                                                    <label class="form-label text-xs">No. batch</label>
                                                                    <input type="text" name="lot_number" class="form-input text-xs" value="{{ $lot->lot_number }}">
                                                                </div>
                                                                <x-stock-remaining-fields
                                                                    :stock-unit="$material->unit"
                                                                    :current-qty="$lot->quantity_remaining"
                                                                    :max-qty="$lot->quantity_received"
                                                                    :compact="true"
                                                                />
                                                                <p class="form-hint -mt-1">Maks. {{ $format::number($lot->quantity_received, 2) }} {{ $material->unit }} (jumlah masuk batch).</p>
                                                                <div>
                                                                    <label class="form-label text-xs">Harga/satuan</label>
                                                                    <x-rupiah-input name="unit_cost" :value="$lot->unit_cost" class="text-xs" />
                                                                </div>
                                                                <button type="submit" class="btn-primary btn-sm w-full">Simpan batch</button>
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
                            @else
                                <p class="mt-3 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                    Belum ada stok sisa. Pakai <strong>Ubah stok sisa</strong> atau <strong>+ Tambah stok</strong>.
                                </p>
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

@push('modals')
    @include('materials.partials.history-modal', [
        'stockLogs' => $stockLogs ?? collect(),
        'format' => $format,
    ])
@endpush
