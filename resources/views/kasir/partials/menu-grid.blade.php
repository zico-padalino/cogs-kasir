@props(['products', 'format', 'menuCategoryLabels' => []])

<div class="pos-product-grid">
    @forelse ($products as $product)
        @php
            $price = (float) ($product->selling_price > 0 ? $product->selling_price : 0);
            $category = $product->menu_category ?: 'lainnya';
            $categoryLabel = $menuCategoryLabels[$category] ?? ucfirst($category);
            $searchKey = strtolower($product->name.' '.$product->sku.' '.$categoryLabel.' '.($product->description ?? ''));
            $maxQty = max(1, (int) $product->availableQuantity());
            $canAdd = $price > 0;
        @endphp
        <article
            class="pos-product-card"
            data-kasir-product="{{ $searchKey }}"
            data-menu-category="{{ $category }}"
            data-product-id="{{ $product->id }}"
            data-product-name="{{ $product->name }}"
            data-product-sku="{{ $product->sku }}"
            data-product-price="{{ $canAdd ? $format::rupiah($price) : 'Atur harga' }}"
            data-product-price-value="{{ $price }}"
            data-product-image="{{ $product->imageUrl() }}"
            data-product-desc="{{ $product->description ?? 'Belum ada deskripsi menu.' }}"
            data-product-stock="{{ $format::number($product->availableQuantity(), 0) }}"
            data-product-max="{{ $maxQty }}"
            data-product-edit-url="{{ route('kasir.products.edit', $product) }}"
        >
            <button
                type="button"
                class="pos-product-card-media"
                data-kasir-open-add
                aria-label="Tambah {{ $product->name }}"
                @disabled(! $canAdd)
            >
                <x-product-image :product="$product" :eager="$loop->index < 6" decorative class="pos-product-card-image" />
                @if (! $canAdd)
                    <span class="pos-product-card-badge">Atur harga</span>
                @endif
            </button>

            <div class="pos-product-card-body">
                <button type="button" class="pos-product-card-info" data-kasir-open-detail>
                    <p class="pos-product-category-label">{{ $categoryLabel }}</p>
                    <h3 class="pos-product-name">{{ $product->name }}</h3>
                    <p class="pos-product-meta">Stok {{ $format::number($product->availableQuantity(), 0) }}</p>
                </button>

                <div class="pos-product-card-foot">
                    <span class="pos-product-price">{{ $canAdd ? $format::rupiah($price) : 'Atur harga' }}</span>
                    @if ($canAdd)
                        <button
                            type="button"
                            class="pos-product-add"
                            data-kasir-open-add
                            aria-label="Tambah {{ $product->name }}"
                        >
                            <span aria-hidden="true">+</span>
                        </button>
                    @else
                        <a
                            href="{{ route('kasir.products.edit', $product) }}"
                            class="pos-product-setup"
                            aria-label="Atur harga {{ $product->name }}"
                        >
                            ⚙
                        </a>
                    @endif
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
