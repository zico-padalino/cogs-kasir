@props(['products', 'format'])

<div class="order-menu-toolbar">
    <input
        type="search"
        data-order-search
        class="order-search-input"
        placeholder="Cari menu..."
        autocomplete="off"
    >
</div>

<div class="order-menu-grid">
    @forelse ($products as $product)
        @php
            $price = $product->selling_price > 0 ? $product->selling_price : $product->standard_cost;
            $searchKey = strtolower($product->name.' '.$product->sku);
            $addonsPayload = $product->relationLoaded('addons')
                ? $product->addons->where('is_active', true)->values()->map(fn ($addon) => [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'price' => (float) $addon->selling_price,
                    'price_label' => '+'.$format::rupiah($addon->selling_price, 0),
                ])->all()
                : [];
        @endphp
        <article
            class="order-product-card"
            role="button"
            tabindex="0"
            data-order-product="{{ $searchKey }}"
            data-order-open-modal
            data-product-id="{{ $product->id }}"
            data-product-name="{{ $product->name }}"
            data-product-price="{{ $format::rupiah($price) }}"
            data-product-price-value="{{ $price }}"
            data-product-image="{{ $product->imageUrl() }}"
            data-product-desc="{{ $product->description ?: 'Belum ada deskripsi menu.' }}"
            data-product-addons="{{ json_encode($addonsPayload, JSON_UNESCAPED_UNICODE) }}"
            data-product-can-add="{{ $price > 0 ? '1' : '0' }}"
        >
            <div class="order-product-media">
                <x-product-image :product="$product" class="order-product-image" />
                @if ($price <= 0)
                    <span class="order-product-badge">Atur harga</span>
                @endif
                @if (count($addonsPayload) > 0)
                    <span class="order-product-addon-badge">+add-on</span>
                @endif
            </div>
            <div class="order-product-body">
                <h3 class="order-product-name">{{ $product->name }}</h3>
                @if ($product->description)
                    <p class="order-product-desc">{{ \Illuminate\Support\Str::limit($product->description, 48) }}</p>
                @else
                    <p class="order-product-meta">{{ $product->sku }}</p>
                @endif
                <div class="order-product-foot">
                    <span class="order-product-price">{{ $format::rupiah($price) }}</span>
                    <span class="order-product-add {{ $price <= 0 ? 'is-disabled' : '' }}">
                        {{ $price > 0 ? 'Lihat' : 'Detail' }}
                    </span>
                </div>
            </div>
        </article>
    @empty
        <div class="order-empty order-menu-empty">
            <p>Menu belum tersedia</p>
            <p class="order-empty-hint">Hubungi staf atau pesan langsung di kasir</p>
        </div>
    @endforelse
</div>

<div class="order-modal hidden" data-order-modal aria-hidden="true">
    <div class="order-modal-backdrop" data-order-close-modal></div>
    <div class="order-modal-panel order-detail-modal-panel" role="dialog" aria-modal="true" aria-labelledby="order-modal-title">
        <button type="button" class="order-modal-close order-detail-modal-close" data-order-close-modal aria-label="Tutup">×</button>

        <div class="order-detail-hero">
            <img src="" alt="" class="order-detail-image" data-order-modal-image>
        </div>

        <div class="order-detail-info">
            <h2 id="order-modal-title" class="order-modal-title" data-order-modal-title></h2>
            <p class="order-modal-price" data-order-modal-price></p>
            <p class="order-detail-desc" data-order-modal-desc></p>
        </div>

        <form action="{{ route('order.menu.items') }}" method="POST" class="order-modal-form" data-order-modal-form>
            @csrf
            <input type="hidden" name="product_id" value="" data-order-modal-product-id>

            <div class="order-modal-field" data-order-can-add-only>
                <label class="order-modal-label" for="order-modal-qty">Jumlah</label>
                <div class="order-qty-stepper">
                    <button type="button" class="order-qty-btn" data-order-qty-minus aria-label="Kurangi">−</button>
                    <input
                        id="order-modal-qty"
                        type="number"
                        name="quantity"
                        value="1"
                        min="1"
                        class="order-qty-input order-modal-qty"
                        inputmode="numeric"
                        data-order-modal-qty
                    >
                    <button type="button" class="order-qty-btn" data-order-qty-plus aria-label="Tambah">+</button>
                </div>
            </div>

            <div class="order-modal-field hidden" data-order-addons-wrap data-order-can-add-only>
                <p class="order-modal-label">Add-on tambahan</p>
                <div class="pos-addon-list" data-order-addons></div>
            </div>

            <div class="order-modal-field" data-order-can-add-only>
                <label class="order-modal-label" for="order-modal-notes">Catatan</label>
                <textarea
                    id="order-modal-notes"
                    name="notes"
                    rows="2"
                    maxlength="255"
                    class="order-item-note-input"
                    placeholder="Opsional: tanpa gula, bungkus terpisah..."
                ></textarea>
            </div>

            <button type="submit" class="btn-primary order-modal-submit" data-order-can-add-only>
                Tambah ke Pesanan
            </button>

            <p class="order-detail-unavailable hidden" data-order-unavailable>
                Menu ini belum bisa dipesan. Silakan hubungi kasir.
            </p>
        </form>
    </div>
</div>
