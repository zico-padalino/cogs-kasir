@extends('layouts.kasir')

@section('title', 'Atur Menu')
@section('heading', 'Atur Menu')
@section('subheading', $product->name)

@section('content')
    <div class="mx-auto max-w-lg px-1">
        <a href="{{ route('kasir.products.index') }}" class="page-back">← Kembali ke Kelola Menu</a>

        <div class="card mt-4 overflow-hidden p-0">
            <div class="kasir-product-preview">
                <img
                    src="{{ $product->imageUrl() }}"
                    alt="{{ $product->name }}"
                    class="kasir-product-preview-image"
                    data-kasir-product-preview
                    data-fallback-src="{{ asset('images/products/default-food.svg') }}"
                    onerror="if(this.dataset.fallbackSrc&&this.src!==this.dataset.fallbackSrc){this.src=this.dataset.fallbackSrc}else{this.onerror=null}"
                >
                <div class="kasir-product-preview-body">
                    <h1 class="text-lg font-bold text-slate-900">{{ $product->name }}</h1>
                    <p class="text-sm text-slate-500">{{ $product->sku }}</p>
                    @if ($product->hasCustomUpload())
                        <p class="mt-1 text-xs font-medium text-emerald-700">Gambar upload aktif</p>
                    @elseif ($product->image_path)
                        <p class="mt-1 text-xs text-slate-500">Ilustrasi bawaan</p>
                    @else
                        <p class="mt-1 text-xs text-amber-700">Belum ada gambar khusus</p>
                    @endif
                </div>
            </div>

            <div class="border-b border-slate-100 bg-slate-50 px-4 py-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-500">Harga jual</span>
                    <span class="font-semibold text-slate-900">
                        {{ (float) $product->selling_price > 0 ? $format::rupiah($product->selling_price) : 'Belum diatur' }}
                    </span>
                </div>
                @if ($unitHpp > 0)
                    <p class="mt-1 text-xs text-slate-500">
                        Modal {{ $format::rupiah($unitHpp) }}
                        @if ((float) $product->selling_price > 0)
                            · Untung {{ $format::rupiah($grossMargin) }} ({{ $marginPercent }}%)
                        @endif
                    </p>
                @endif
                <p class="mt-2 text-xs text-slate-500">Ubah harga di modul Hitung Biaya → Produk.</p>
            </div>

            <form
                action="{{ route('kasir.products.update', $product) }}"
                method="POST"
                enctype="multipart/form-data"
                class="space-y-5 p-4 sm:p-5"
                data-kasir-product-edit
            >
                @csrf
                @method('PUT')

                {{-- Hapus gambar upload -- tampil hanya jika ada gambar yang diupload --}}
                @if ($product->hasCustomUpload())
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-rose-900">Gambar upload aktif</p>
                            <p class="mt-0.5 text-xs text-rose-700">Centang lalu tekan Simpan Menu untuk menghapus dan kembali ke ilustrasi bawaan.</p>
                        </div>
                        <label class="flex shrink-0 items-center gap-2">
                            <input
                                type="checkbox"
                                name="remove_image"
                                value="1"
                                class="h-5 w-5 rounded border-rose-400 text-rose-600"
                                id="remove_image_check"
                                data-kasir-remove-image
                                @checked(old('remove_image'))
                            >
                            <span class="text-sm font-semibold text-rose-800 select-none" for="remove_image_check">Hapus</span>
                        </label>
                    </div>
                @endif

                <div data-kasir-image-upload-section>
                    <p class="form-label">Ganti gambar</p>
                    <div class="file-pick-row">
                        <label class="file-pick-btn">
                            <input
                                id="product_image"
                                type="file"
                                name="image"
                                accept="image/*"
                                data-kasir-product-image
                            >
                            <span>Pilih gambar</span>
                        </label>
                        <span class="file-pick-name" data-kasir-product-filename>Belum ada file dipilih</span>
                    </div>
                    <p class="form-hint">Upload JPG/PNG/WebP maks. 2 MB, lalu tekan <strong>Simpan Menu</strong>.</p>
                    @error('image')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
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
                                    @checked(! $product->hasCustomUpload() && old('preset_image', $product->image_path) === $path)
                                    data-kasir-preset-radio
                                >
                                <img
                                    src="{{ asset($path) }}"
                                    alt="{{ $label }}"
                                    class="kasir-preset-thumb"
                                    onerror="this.style.opacity='0.3'"
                                >
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Hidden checkbox placeholder saat tidak ada upload aktif --}}
                @unless ($product->hasCustomUpload())
                    <input type="hidden" name="remove_image" value="0">
                @endunless

                <div>
                    <label class="form-label">Kategori Menu (POS)</label>
                    <select name="menu_category" class="form-input">
                        <option value="">— Pilih kategori —</option>
                        @foreach ($menuCategories as $key => $label)
                            <option value="{{ $key }}" @selected(old('menu_category', $product->menu_category) === $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <p class="form-hint">Untuk filter tab Minuman, Makanan, Pastry, dll.</p>
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
                    <a href="{{ route('kasir.products.index') }}" class="btn-secondary w-full sm:w-auto">Batal</a>
                </div>
            </form>
        </div>
    </div>
@endsection
