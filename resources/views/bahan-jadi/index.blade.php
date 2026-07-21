@extends('layouts.app')

@section('title', 'Bahan Jadi')
@section('heading', 'Bahan Jadi')
@section('subheading', 'Hasil olahan dari bahan baku — bisa dipakai di resep menu')

@section('content')
    <div class="module-page">
        @if (session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('error') }}</div>
        @endif

        <x-module-form-card :step="2" icon="🥣" title="Tambah Bahan Jadi" description="Contoh: adonan, sirup, saus — stok berkurang saat dipakai di menu.">
            <form action="{{ route('bahan-jadi.store') }}" method="POST" class="material-add-form">
                @csrf
                <div>
                    <label class="form-label">Nama bahan jadi</label>
                    <input type="text" name="name" class="form-input" required placeholder="Adonan pizza" value="{{ old('name') }}">
                </div>

                <x-unit-picker
                    :selected="old('unit_preset', 'gr')"
                    :custom-value="old('unit_custom', '')"
                />

                <x-material-purchase-fields />

                <button type="submit" class="btn-primary w-full py-3 font-semibold">Simpan Bahan Jadi</button>
            </form>
        </x-module-form-card>

        <x-table-card title="Daftar Bahan Jadi" :subtitle="$items->count().' item'">
            @if ($items->isNotEmpty())
                <div class="space-y-3 p-4 sm:p-5">
                    @foreach ($items as $item)
                        <div class="module-item-card material-card">
                            <div class="material-card__top">
                                <div class="min-w-0 flex-1">
                                    <p class="text-base font-bold text-slate-900">{{ $item->name }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span class="module-stat-pill module-stat-pill--stock">
                                            {{ $format::number($item->available_qty) }} {{ $item->unit }}
                                        </span>
                                        @if ($item->avg_cost > 0)
                                            <span class="module-stat-pill module-stat-pill--price">
                                                {{ $format::rupiah($item->avg_cost) }}/{{ $item->unit }}
                                            </span>
                                        @endif
                                        @if ($item->bill_of_materials_count > 0)
                                            <span class="module-stat-pill">Resep {{ $item->bill_of_materials_count }} bahan</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="material-card__actions">
                                <a href="{{ route('products.show', $item) }}" class="btn-outline btn-sm text-center">
                                    Resep dari bahan
                                </a>

                                <details class="material-card__action">
                                    <summary class="btn-outline btn-sm cursor-pointer list-none text-center">Edit / Stok</summary>
                                    <form action="{{ route('bahan-jadi.update', $item) }}" method="POST" class="material-panel">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="form-label">Nama</label>
                                            <input type="text" name="name" class="form-input" required value="{{ old('name', $item->name) }}">
                                        </div>
                                        <x-unit-picker
                                            :selected="old('unit_preset', $units::guessPreset($item->unit))"
                                            :custom-value="old('unit_custom', $units::guessPreset($item->unit) === 'other' ? $item->unit : '')"
                                        />
                                        <p class="form-hint">Isi pembelian di bawah hanya jika menambah stok.</p>
                                        <x-material-purchase-fields />
                                        <button type="submit" class="btn-primary w-full">Simpan</button>
                                    </form>
                                </details>

                                <form action="{{ route('bahan-jadi.destroy', $item) }}" method="POST" onsubmit="return confirm('Hapus bahan jadi ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-outline-danger btn-sm w-full">Hapus</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="px-4 py-10 text-center text-sm text-slate-500 sm:px-5">Belum ada bahan jadi. Tambah di form atas.</p>
            @endif
        </x-table-card>
    </div>
@endsection
