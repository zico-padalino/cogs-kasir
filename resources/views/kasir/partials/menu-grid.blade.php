@props(['products', 'format', 'menuCategoryLabels' => []])

<div class="pos-product-grid">
    @forelse ($products as $product)
        @php
            $price = $product->selling_price > 0 ? $product->selling_price : 0;
            $category = $product->menu_category ?: 'lainnya';
            $categoryLabel = $menuCategoryLabels[$category] ?? ucfirst($category);
            $searchKey = strtolower($product->name.' '.$product->sku.' '.$categoryLabel.' '.($product->description ?? ''));
            $maxQty = max(1, (int) $product->availableQuantity());
        @endphp
        <article
            class="pos-product-card"
            data-kasir-product="{{ $searchKey }}"
            data-menu-category="{{ $category }}"
            data-product-id="{{ $product->id }}"
            data-product-name="{{ $product->name }}"
            data-product-sku="{{ $product->sku }}"
            data-product-price="{{ $price > 0 ? $format::rupiah($price) : 'Atur harga' }}"
            data-product-price-value="{{ $price }}"
            data-product-image="{{ $product->imageUrl() }}"
            data-product-desc="{{ $product->description ?? 'Belum ada deskripsi menu.' }}"
            data-product-stock="{{ $format::number($product->availableQuantity(), 0) }}"
            data-product-max="{{ $maxQty }}"
            data-product-edit-url="{{ route('kasir.products.edit', $product) }}"
        >
            <button type="button" class="pos-product-card-media" data-kasir-open-detail aria-label="Detail {{ $product->name }}">
                <x-product-image :product="$product" :eager="$loop->index < 6" class="pos-product-card-image" />
                @if ($price <= 0)
                    <span class="pos-product-card-badge">Atur harga</span>
                @endif
                <span class="pos-product-category">{{ $categoryLabel }}</span>
            </button>

            <div class="pos-product-card-body">
                <button type="button" class="pos-product-card-info" data-kasir-open-detail>
                    <h3 class="pos-product-name">{{ $product->name }}</h3>
                    @if ($product->description)
                        <p class="pos-product-desc">{{ Str::limit($product->description, 48) }}</p>
                    @endif
                </button>

                <div class="pos-product-card-foot">
                    <span class="pos-product-price">{{ $price > 0 ? $format::rupiah($price) : 'Atur harga' }}</span>
                    <div class="pos-product-card-actions">
                        <a href="{{ route('kasir.products.edit', $product) }}" class="pos-product-edit" title="Atur menu">⚙</a>
                        <button
                            type="button"
                            class="pos-product-add"
                            data-kasir-open-add
                            @disabled($price <= 0)
                        >
                            +
                        </button>
                    </div>
                </div>
            </div>
        </article>
    @empty
        <div class="pos-product-empty">
            <p>Belum ada menu siap jual</p>
            <p class="pos-product-empty-hint">Tambah stok produk jadi di modul COGS</p>
        </div>
    @endforelse
</div>

@include('kasir.partials.item-modals')
