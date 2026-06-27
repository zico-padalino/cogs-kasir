@extends('layouts.app')

@section('title', 'Tambah Produk')
@section('heading', 'Tambah Produk Baru')
@section('subheading', 'Isi data produk — bahan baku atau barang jadi')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-step-header number="2" title="Tambah Produk"
            description="Pilih jenis: Bahan Baku untuk stok masuk, Barang Jadi untuk produk akhir." />

        <div class="card">
            <form action="{{ route('products.store') }}" method="POST" class="space-y-5">
                @csrf

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label class="form-label">Kode Produk (SKU)</label>
                        <input type="text" name="sku" id="sku" value="{{ old('sku') }}" class="form-input" required placeholder="RM-FLOUR-001">
                    </div>
                    <div>
                        <label class="form-label">Satuan</label>
                        <input type="text" name="unit" id="unit" value="{{ old('unit', 'kg') }}" class="form-input" placeholder="kg, pcs, liter">
                    </div>
                </div>

                <div>
                    <label class="form-label">Nama Produk</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" class="form-input" required placeholder="Tepung Terigu">
                </div>

                <div>
                    <label class="form-label">Jenis Produk</label>
                    <select name="type" id="type" class="form-input" required>
                        @foreach ($productTypes as $type)
                            <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Bahan Baku = dibeli & disimpan. Barang Jadi = hasil produksi.</p>
                </div>

                <details class="rounded-lg border border-slate-200 p-4">
                    <summary class="cursor-pointer text-sm font-medium text-slate-700">Pengaturan lanjutan (opsional)</summary>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="form-label">Cara Hitung Harga Stok</label>
                            <select name="costing_method" class="form-input">
                                @foreach ($costingMethods as $method)
                                    <option value="{{ $method->value }}" @selected(old('costing_method', 'weighted_average') === $method->value)>{{ $method->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                <div>
                    <label class="form-label">Biaya Standar (Rp)</label>
                    <x-rupiah-input name="standard_cost" :value="old('standard_cost', 0)" placeholder="0" />
                </div>
                        <div>
                            <label class="form-label">Deskripsi</label>
                            <textarea name="description" rows="2" class="form-input">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </details>

                <div class="form-actions pt-2">
                    <button type="submit" class="btn-primary">Simpan Produk</button>
                    <a href="{{ route('products.index') }}" class="btn-secondary">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
