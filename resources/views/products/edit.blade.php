@extends('layouts.app')

@section('title', 'Ubah Menu')
@section('heading', 'Ubah Menu')
@section('subheading', $product->name)

@section('content')
    @php
        $unitPreset = old('unit_preset', \App\Support\MaterialUnits::guessMenuPreset($product->unit));
        $unitCustom = old('unit_custom', $unitPreset === 'other' ? $product->unit : '');
    @endphp
    <div class="mx-auto max-w-lg">
        <div class="card p-4 sm:p-5">
            <form action="{{ route('products.update', $product) }}" method="POST" class="space-y-4">
                @csrf @method('PUT')
                <input type="hidden" name="type" value="{{ $product->type->value }}">
                <input type="hidden" name="sku" value="{{ $product->sku }}">

                <div>
                    <label class="form-label">Nama menu</label>
                    <input type="text" name="name" class="form-input" value="{{ old('name', $product->name) }}" required>
                </div>

                <x-menu-unit-select
                    :selected="$unitPreset"
                    :custom-value="$unitCustom"
                />

                <div>
                    <label class="form-label">Modal per {{ $product->unit }}</label>
                    <input type="text" class="form-input bg-slate-50" value="{{ $product->unit_hpp > 0 ? $format::rupiah($product->unit_hpp, 0) : 'Belum dihitung — lengkapi resep dulu' }}" readonly>
                </div>

                <p class="text-sm text-slate-600">
                    Harga jual diatur di
                    <a href="{{ route('menu-pricing.index') }}" class="font-semibold text-brand-600">Harga Jual →</a>
                </p>

                <input type="hidden" name="is_menu_item" value="1">

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" class="rounded" @checked(old('is_active', $product->is_active))>
                    <span class="text-sm">Menu masih aktif</span>
                </label>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Simpan</button>
                    <a href="{{ route('products.show', $product) }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
