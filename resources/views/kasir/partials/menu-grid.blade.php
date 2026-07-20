@props(['products', 'format', 'menuCategoryLabels' => []])

<div class="pos-product-grid">
    @forelse ($products as $product)
        @php
            $price = (float) ($product->selling_price > 0 ? $product->selling_price : 0);
            $category = $product->menu_category ?: 'lainnya';
            $categoryLabel = $menuCategoryLabels[$category] ?? ucfirst($category);
            $searchKey = strtolower($product->name.' '.$product->sku.' '.$categoryLabel.' '.($product->description ?? ''));
            $inStock = $product->isMenuInStock();
            $canAdd = $price > 0 && $inStock;
            $soldOut = $price > 0 && ! $inStock;
            $addonsPayload = $product->addons
                ->where('is_active', true)
                ->values()
                ->map(fn ($addon) => [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'price' => (float) $addon->selling_price,
                    'price_label' => '+'.$format::rupiah($addon->selling_price, 0),
                ])
                ->all();
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
            data-product-edit-url="{{ route('kasir.products.edit', $product) }}"
            data-product-addons="{{ json_encode($addonsPayload, JSON_UNESCAPED_UNICODE) }}"
        >
            <button
                type="button"
                class="pos-product-card-media"
                data-kasir-open-add
                aria-label="Tambah {{ $product->name }}"
                @disabled(! $canAdd)
            >
                <x-product-image :product="$product" :eager="$loop->index < 6" decorative class="pos-product-card-image" />
                @if ($soldOut)
                    <span class="pos-product-card-badge !bg-rose-600">Habis</span>
                @elseif (! $canAdd)
                    <span class="pos-product-card-badge">Atur harga</span>
                @elseif (count($addonsPayload) > 0)
                    <span class="pos-product-card-badge !bg-brand-600">Add-on</span>
                @endif
                @if ($canAdd)
                    <span class="pos-product-add-fab" aria-hidden="true">+</span>
                @endif
            </button>

            <div class="pos-product-card-body">
                <button type="button" class="pos-product-card-info" data-kasir-open-detail>
                    <p class="pos-product-category-label">{{ $categoryLabel }}</p>
                    <h3 class="pos-product-name">{{ $product->name }}</h3>
                </button>

                <div class="pos-product-card-foot">
                    <span class="pos-product-price">
                        @if ($soldOut)
                            Habis
                        @elseif ($canAdd)
                            {{ $format::rupiah($price) }}
                        @else
                            Atur harga
                        @endif
                    </span>
                    @if ($canAdd)
                        <button
                            type="button"
                            class="pos-product-add"
                            data-kasir-open-add
                            aria-label="Tambah {{ $product->name }}"
                        >
                            <span aria-hidden="true">+</span>
                        </button>
                    @elseif ($soldOut)
                        <span class="pos-product-setup text-rose-600" title="Stok habis">∅</span>
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
            <p class="pos-product-empty-hint">Aktifkan menu dan atur harga di modul Hitung Biaya</p>
        </div>
    @endforelse
</div>
