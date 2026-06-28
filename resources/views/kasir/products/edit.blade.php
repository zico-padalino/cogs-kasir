@extends('layouts.kasir')

@section('title', 'Atur Menu')
@section('heading', 'Atur Menu')
@section('subheading', $product->name)

@section('content')
    <div class="mx-auto max-w-lg px-1">
        <a href="{{ route('kasir.index') }}" class="page-back">← Kembali ke Kasir</a>

        <div class="card mt-4 overflow-hidden p-0">
            <div class="kasir-product-preview">
                <x-product-image :product="$product" class="kasir-product-preview-image" />
                <div class="kasir-product-preview-body">
                    <h1 class="text-lg font-bold text-slate-900">{{ $product->name }}</h1>
                    <p class="text-sm text-slate-500">{{ $product->sku }} · Stok {{ $format::number($product->availableQuantity(), 0) }}</p>
                </div>
            </div>

            <form action="{{ route('kasir.products.update', $product) }}" method="POST" enctype="multipart/form-data" class="space-y-5 p-4 sm:p-5">
                @csrf
                @method('PUT')

                <div>
                    <label class="form-label">Gambar Menu</label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="form-input file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700">
                    <p class="form-hint">Upload JPG/PNG/WebP maks. 2 MB</p>
                </div>

                <div>
                    <p class="form-label">Atau pilih ilustrasi bawaan</p>
                    <div class="kasir-preset-grid">
                        @foreach ($presets as $path => $label)
                            <label class="kasir-preset-option">
                                <input
                                    type="radio"
                                    name="preset_image"
                                    value="{{ $path }}"
                                    class="sr-only"
                                    @checked(old('preset_image', $product->image_path) === $path)
                                >
                                <img src="{{ asset($path) }}" alt="{{ $label }}" class="kasir-preset-thumb">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <label class="flex items-center gap-2">
                    <input type="checkbox" name="remove_image" value="1" class="rounded" @checked(old('remove_image'))>
                    <span class="text-sm text-slate-600">Hapus gambar (pakai default)</span>
                </label>

                <div>
                    <x-rupiah-input name="selling_price" label="Harga Jual (Rp)" :value="old('selling_price', $product->selling_price)" required />
                </div>

                <div>
                    <label class="form-label">Detail / Deskripsi Menu</label>
                    <textarea
                        name="description"
                        rows="4"
                        maxlength="1000"
                        class="form-input"
                        placeholder="Contoh: Roti premium tanpa pengawet, best seller..."
                    >{{ old('description', $product->description) }}</textarea>
                    <p class="form-hint">Tampil di kasir saat pelanggan/kasir melihat detail produk.</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary w-full sm:w-auto">Simpan Menu</button>
                    <a href="{{ route('kasir.index') }}" class="btn-secondary w-full sm:w-auto">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
