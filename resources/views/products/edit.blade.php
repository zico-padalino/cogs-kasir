@extends('layouts.app')

@section('title', 'Edit Produk')
@section('heading', 'Edit Produk')
@section('subheading', $product->name)

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-step-header number="2" title="Edit Produk" description="Perbarui data produk." />

        <div class="card">
            <form action="{{ route('products.update', $product) }}" method="POST" class="space-y-5">
                @csrf @method('PUT')

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="form-label">Kode Produk (SKU)</label>
                        <input type="text" name="sku" class="form-input" value="{{ old('sku', $product->sku) }}" required>
                    </div>
                    <div>
                        <label class="form-label">Satuan</label>
                        <input type="text" name="unit" class="form-input" value="{{ old('unit', $product->unit) }}">
                    </div>
                </div>

                <div>
                    <label class="form-label">Nama Produk</label>
                    <input type="text" name="name" class="form-input" value="{{ old('name', $product->name) }}" required>
                </div>

                <div>
                    <label class="form-label">Jenis Produk</label>
                    <select name="type" class="form-input" required>
                        @foreach ($productTypes as $type)
                            <option value="{{ $type->value }}" @selected(old('type', $product->type->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="form-label">Cara Hitung Harga Stok</label>
                        <select name="costing_method" class="form-input">
                            @foreach ($costingMethods as $method)
                                <option value="{{ $method->value }}" @selected(old('costing_method', $product->costing_method->value) === $method->value)>{{ $method->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Estimasi Biaya (Rp)</label>
                        <x-rupiah-input name="standard_cost" :value="old('standard_cost', $product->standard_cost)" />
                        <p class="form-hint">Perkiraan awal sebelum HPP terhitung dari produksi.</p>
                    </div>
                    <div>
                        <label class="form-label">HPP / Unit (Rp)</label>
                        <input type="text" class="form-input bg-slate-50" value="{{ $format::rupiah($product->effectiveUnitHpp(), 2) }}" readonly>
                        <p class="form-hint">Terisi otomatis setelah produksi selesai. COGS = nilai HPP ini.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <x-rupiah-input name="selling_price" label="Harga Jual Menu (Rp) — atur di sini" :value="old('selling_price', $product->selling_price)" />
                        <p class="form-hint">Harga ini tampil di Kasir/POS. Stok menu diatur di modul Kasir.</p>
                    </div>
                </div>

                @if (in_array($product->type->value, ['finished_good', 'semi_finished']))
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_menu_item" value="1" class="rounded" @checked(old('is_menu_item', $product->is_menu_item))>
                        <span class="text-sm">Tampilkan sebagai menu di Kasir (stok jadi produk dijual)</span>
                    </label>
                @endif

                <div>
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" rows="2" class="form-input">{{ old('description', $product->description) }}</textarea>
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_active" value="1" class="rounded" @checked(old('is_active', $product->is_active))>
                    <span class="text-sm">Produk aktif</span>
                </label>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Simpan Perubahan</button>
                    <a href="{{ route('products.show', $product) }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
