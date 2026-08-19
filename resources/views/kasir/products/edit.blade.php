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

                {{-- Gambar upload aktif: tombol unduh + hapus --}}
                @if ($product->hasCustomUpload())
                    <div class="space-y-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <p class="text-sm font-semibold text-emerald-900">Gambar upload aktif</p>
                        <div class="flex flex-wrap gap-2">
                            <a
                                href="{{ $product->imageUrl() }}"
                                download="{{ Str::slug($product->name).'.'.pathinfo($product->image_path, PATHINFO_EXTENSION) }}"
                                target="_blank"
                                rel="noopener"
                                class="btn-secondary btn-sm inline-flex items-center gap-1.5 no-underline"
                                data-kasir-download-image
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2M7 10l5 5 5-5M12 15V3" />
                                </svg>
                                Unduh gambar
                            </a>
                            <label class="btn-sm inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                                <input
                                    type="checkbox"
                                    name="remove_image"
                                    value="1"
                                    class="h-4 w-4 rounded border-rose-400 text-rose-600"
                                    id="remove_image_check"
                                    data-kasir-remove-image
                                    @checked(old('remove_image'))
                                >
                                Hapus gambar
                            </label>
                        </div>
                        <p class="text-xs text-emerald-700">Centang <strong>Hapus gambar</strong> lalu tekan Simpan Menu untuk kembali ke ilustrasi bawaan.</p>
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
