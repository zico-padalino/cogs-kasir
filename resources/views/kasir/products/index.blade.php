@extends('layouts.kasir')

@section('title', 'Kelola Menu')
@section('heading', 'Kelola Menu')
@section('subheading', 'Atur gambar, kategori, deskripsi, dan status habis menu di POS')

@section('content')
    <div class="page-toolbar">
        <p class="text-sm text-slate-500">{{ $products->count() }} item menu · centang <strong>Habis</strong> supaya tidak bisa dipesan di kasir & QR</p>
        <a href="{{ route('kasir.index') }}" class="btn-outline btn-sm shrink-0">← Kembali ke POS</a>
    </div>

    @if ($products->isNotEmpty())
        <div class="kasir-menu-admin-toolbar">
            <input
                type="search"
                class="form-input kasir-menu-admin-search"
                placeholder="Cari menu..."
                data-kasir-menu-admin-search
                autocomplete="off"
            >
            <div class="kasir-menu-admin-tabs" role="tablist" data-kasir-menu-admin-tabs>
                <button type="button" class="kasir-menu-admin-tab is-active" data-kasir-menu-admin-category="all">Semua</button>
                @foreach ($menuCategories as $key => $label)
                    <button type="button" class="kasir-menu-admin-tab" data-kasir-menu-admin-category="{{ $key }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="kasir-menu-admin-list" data-kasir-menu-admin-list>
            @foreach ($products as $product)
                @php
                    $price = (float) $product->selling_price;
                    $hpp = $product->effectiveUnitHpp();
                    $category = $product->menu_category ?: 'lainnya';
                    $categoryLabel = $menuCategories[$category] ?? ucfirst($category);
                    $searchKey = strtolower($product->name.' '.$product->sku.' '.$categoryLabel);
                    $isSoldOut = (bool) $product->is_sold_out;
                @endphp
                <article
                    class="kasir-menu-admin-row {{ $isSoldOut ? 'is-sold-out' : '' }}"
                    data-kasir-menu-admin-item
                    data-menu-category="{{ $category }}"
                    data-search="{{ $searchKey }}"
                >
                    <x-product-image :product="$product" class="kasir-menu-admin-thumb" />

                    <div class="kasir-menu-admin-body">
                        <div class="kasir-menu-admin-head">
                            <h2 class="kasir-menu-admin-name">{{ $product->name }}</h2>
                            @if (! $product->is_active)
                                <span class="badge badge-slate">Nonaktif</span>
                            @elseif ($price <= 0)
                                <span class="badge badge-amber">Atur harga di Hitung Biaya</span>
                            @endif
                            <span class="badge badge-rose {{ $isSoldOut ? '' : 'hidden' }}" data-sold-out-badge>Habis</span>
                        </div>
                        <p class="kasir-menu-admin-meta">
                            {{ $product->sku }}
                            · {{ $categoryLabel }}
                        </p>
                        @if ($product->description)
                            <p class="kasir-menu-admin-desc">{{ Str::limit($product->description, 80) }}</p>
                        @endif
                        <p class="kasir-menu-admin-price">
                            @if ($price > 0)
                                {{ $format::rupiah($price) }}
                                @if ($hpp > 0)
                                    <span class="block text-xs font-normal text-slate-500">Modal {{ $format::rupiah($hpp) }} · untung {{ $format::rupiah($price - $hpp) }}</span>
                                @endif
                            @else
                                <span class="text-slate-500">Harga belum diatur di Hitung Biaya</span>
                            @endif
                        </p>
                    </div>

                    <div class="kasir-menu-admin-actions">
                        <label class="kasir-menu-sold-out-toggle">
                            <input
                                type="checkbox"
                                class="kasir-menu-sold-out-checkbox"
                                data-sold-out-toggle
                                data-sold-out-url="{{ route('kasir.products.sold-out', $product) }}"
                                @checked($isSoldOut)
                            >
                            <span>Habis</span>
                        </label>
                        <a href="{{ route('kasir.products.edit', $product) }}" class="btn-primary btn-sm kasir-menu-admin-edit">
                            Atur Menu
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <p class="kasir-menu-admin-empty hidden" data-kasir-menu-admin-empty>Tidak ada menu yang cocok dengan filter.</p>
    @else
        <div class="card">
            <div class="empty-state">
                <p>Belum ada produk jadi untuk dijual.</p>
                <p class="empty-hint">Aktifkan menu di modul Hitung Biaya, lalu atur harga jual di sana.</p>
            </div>
        </div>
    @endif
@endsection
