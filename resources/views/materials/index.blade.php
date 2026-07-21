@extends('layouts.app')

@section('title', 'Bahan Baku')
@section('heading', 'Langkah 2: Bahan Baku')
@section('subheading', 'Stok + harga beli — isi sekali langsung jadi')

@section('content')
    <div class="module-page module-step-2">
        <div class="page-toolbar materials-toolbar">
            <p class="text-sm text-slate-500">Kelola bahan baku &amp; stok</p>
            <div class="materials-toolbar__actions">
                <a
                    href="{{ route('materials.pdf', ['autoprint' => 1]) }}"
                    target="_blank"
                    rel="noopener"
                    class="btn-outline btn-sm"
                >
                    PDF Sisa
                </a>
                <button
                    type="button"
                    class="btn-outline btn-sm"
                    data-material-history-open
                >
                    Riwayat
                </button>
            </div>
        </div>

        <x-module-form-card :step="2" title="Tambah Bahan Baku" description="Isi nama, satuan, lalu pembelian.">
            <form action="{{ route('materials.store') }}" method="POST" class="material-add-form">
                @csrf
                <div>
                    <label class="form-label">Nama bahan baku</label>
                    <input type="text" name="name" class="form-input" required placeholder="Bubuk kopi" value="{{ old('name') }}">
                </div>

                <x-unit-picker
                    :selected="old('unit_preset', 'gr')"
                    :custom-value="old('unit_custom', '')"
                />

                <x-material-purchase-fields />

                <button type="submit" class="btn-primary w-full py-3 font-semibold">Simpan Bahan Baku</button>
            </form>
        </x-module-form-card>

        <x-table-card :step="2" title="Daftar Bahan Baku" :subtitle="$materials->count() . ' bahan terdaftar'">
            @if ($materials->isNotEmpty())
                <div class="space-y-3 p-4 sm:p-5" data-materials-list>
                    <div class="materials-search">
                        <input
                            type="search"
                            class="form-input"
                            placeholder="Cari bahan baku..."
                            data-materials-search
                            autocomplete="off"
                        >
                    </div>

                    @foreach ($materials as $material)
                        <div
                            class="module-item-card material-card"
                            data-material-card
                            data-search="{{ strtolower($material->name.' '.$material->unit) }}"
                        >
                            <div class="material-card__top">
                                <div class="min-w-0 flex-1">
                                    <p class="text-base font-bold text-slate-900">{{ $material->name }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span class="module-stat-pill module-stat-pill--stock">
                                            {{ $format::number($material->available_qty) }} {{ $material->unit }}
                                        </span>
                                        @if ($material->avg_cost > 0)
                                            <span class="module-stat-pill module-stat-pill--price">
                                                {{ $format::rupiah($material->avg_cost) }}/{{ $material->unit }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="material-card__actions">
                                <details class="material-card__action">
                                    <summary class="btn-outline btn-sm cursor-pointer list-none text-center">Edit</summary>
                                    <form
                                        action="{{ route('materials.update', $material) }}"
                                        method="POST"
                                        class="material-panel"
                                    >
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="form-label" for="material-name-{{ $material->id }}">Nama bahan</label>
                                            <input
                                                id="material-name-{{ $material->id }}"
                                                type="text"
                                                name="name"
                                                class="form-input"
                                                required
                                                maxlength="255"
                                                value="{{ old('name', $material->name) }}"
                                            >
                                        </div>

                                        <x-unit-picker
                                            :selected="old('unit_preset', $units::guessPreset($material->unit))"
                                            :custom-value="old('unit_custom', $units::guessPreset($material->unit) === 'other' ? $material->unit : '')"
                                        />

                                        <x-material-purchase-fields
                                            :optional="true"
                                            :stock-unit-label="$material->unit"
                                        />

                                        <button type="submit" class="btn-primary w-full py-3 font-semibold">Simpan Bahan Baku</button>
                                    </form>
                                </details>

                                <details class="material-card__action">
                                    <summary class="btn-outline btn-sm cursor-pointer list-none text-center">Stok sisa</summary>
                                    <form
                                        action="{{ route('materials.stock.adjust', $material) }}"
                                        method="POST"
                                        class="material-panel material-panel--amber"
                                    >
                                        @csrf
                                        @method('PUT')
                                        <p class="text-xs text-slate-600">
                                            Sistem sekarang:
                                            <strong>{{ $format::number($material->available_qty) }} {{ $material->unit }}</strong>
                                        </p>
                                        <x-stock-remaining-fields
                                            :stock-unit="$material->unit"
                                            :current-qty="$material->available_qty"
                                        />
                                        <button type="submit" class="btn-primary btn-sm w-full">Simpan stok sisa</button>
                                    </form>
                                </details>

                                <details class="material-card__action material-card__action--primary">
                                    <summary class="btn-primary btn-sm cursor-pointer list-none text-center">+ Stok</summary>
                                    <form action="{{ route('materials.receive') }}" method="POST" class="material-panel material-panel--green">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $material->id }}">
                                        <p class="text-xs text-slate-600">
                                            Satuan: <strong>{{ $material->unit }}</strong>
                                        </p>
                                        <x-material-purchase-fields :compact="true" :stock-unit-label="$material->unit" />
                                        <div>
                                            <label class="form-label text-xs">No. batch</label>
                                            <input type="text" name="lot_number" class="form-input text-sm" placeholder="Opsional">
                                        </div>
                                        <button type="submit" class="btn-primary btn-sm w-full">Simpan stok masuk</button>
                                    </form>
                                </details>
                            </div>

                            <form
                                action="{{ route('materials.destroy', $material) }}"
                                method="POST"
                                class="mt-2"
                                onsubmit="return confirm(@js('Hapus bahan '.$material->name."?\n\nStok & batch ikut terhapus. Jika dipakai di resep, bahan juga hilang dari resep menu tersebut."))"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-outline-danger btn-sm w-full">Hapus bahan</button>
                            </form>

                            @if ($material->inventoryLots->where('quantity_remaining', '>', 0)->isNotEmpty())
                                <div class="material-lots">
                                    <p class="material-lots__label">Batch stok</p>
                                    @foreach ($material->inventoryLots->where('quantity_remaining', '>', 0) as $lot)
                                        <div class="material-lot">
                                            <div class="material-lot__meta">
                                                <span class="font-mono text-[11px] text-slate-400">{{ $lot->lot_number ?: 'Tanpa batch' }}</span>
                                                <span class="font-semibold text-slate-800">{{ $format::number($lot->quantity_remaining) }} {{ $material->unit }}</span>
                                            </div>
                                            <div class="material-lot__row">
                                                <span class="text-xs text-slate-500">Masuk {{ $format::number($lot->quantity_received) }} · {{ $format::rupiah($lot->unit_cost) }}/{{ $material->unit }}</span>
                                            </div>
                                            <details class="mt-2">
                                                <summary class="btn-outline btn-sm w-full cursor-pointer list-none text-center">Edit batch</summary>
                                                <form action="{{ route('materials.lots.update', $lot) }}" method="POST" class="mt-2 space-y-2 rounded-xl border border-slate-200 bg-white p-3">
                                                    @csrf @method('PUT')
                                                    <div>
                                                        <label class="form-label text-xs">No. batch</label>
                                                        <input type="text" name="lot_number" class="form-input text-sm" value="{{ $lot->lot_number }}">
                                                    </div>
                                                    <x-stock-remaining-fields
                                                        :stock-unit="$material->unit"
                                                        :current-qty="$lot->quantity_remaining"
                                                        :max-qty="$lot->quantity_received"
                                                        :compact="true"
                                                    />
                                                    <p class="form-hint">Maks. {{ $format::number($lot->quantity_received) }} {{ $material->unit }}</p>
                                                    <div>
                                                        <label class="form-label text-xs">Harga/satuan</label>
                                                        <x-rupiah-input name="unit_cost" :value="$lot->unit_cost" class="text-sm" />
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
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-3 rounded-lg border border-dashed border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">
                                    Belum ada stok. Pakai <strong>Stok sisa</strong> atau <strong>+ Stok</strong>.
                                </p>
                            @endif
                        </div>
                    @endforeach

                    <div class="module-empty hidden !py-8" data-materials-search-empty>
                        <p class="module-empty__title">Tidak ada bahan yang cocok</p>
                        <p class="module-empty__hint">Coba kata kunci lain.</p>
                    </div>
                </div>

                <x-slot:footer>
                    <p class="text-sm font-medium text-slate-600">Bahan baku sudah cukup? Lanjut daftarkan menu.</p>
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
