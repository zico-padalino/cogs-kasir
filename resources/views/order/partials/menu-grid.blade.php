@props(['products', 'table', 'format'])

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
            $maxQty = max(1, (int) $product->availableQuantity());
        @endphp
        <article
            class="order-product-card"
            data-order-product="{{ $searchKey }}"
            data-product-id="{{ $product->id }}"
            data-product-name="{{ $product->name }}"
            data-product-price="{{ $format::rupiah($price) }}"
            data-product-price-value="{{ $price }}"
            data-product-image="{{ $product->imageUrl() }}"
            data-product-max="{{ $maxQty }}"
        >
            <div class="order-product-media">
                <x-product-image :product="$product" class="order-product-image" />
                @if ($price <= 0)
                    <span class="order-product-badge">Atur harga</span>
                @endif
            </div>
            <div class="order-product-body">
                <h3 class="order-product-name">{{ $product->name }}</h3>
                <p class="order-product-meta">{{ $product->sku }} · Stok {{ $format::number($product->availableQuantity(), 0) }}</p>
                <div class="order-product-foot">
                    <span class="order-product-price">{{ $format::rupiah($price) }}</span>
                    <button
                        type="button"
                        class="order-product-add"
                        data-order-open-modal
                        @disabled($price <= 0)
                    >
                        Tambah
                    </button>
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
    <div class="order-modal-panel" role="dialog" aria-modal="true" aria-labelledby="order-modal-title">
        <div class="order-modal-head">
            <img src="" alt="" class="order-modal-image" data-order-modal-image>
            <div class="min-w-0 flex-1">
                <h2 id="order-modal-title" class="order-modal-title" data-order-modal-title></h2>
                <p class="order-modal-price" data-order-modal-price></p>
            </div>
            <button type="button" class="order-modal-close" data-order-close-modal aria-label="Tutup">×</button>
        </div>

        <form action="{{ route('order.table.items', $table->barcode_token) }}" method="POST" class="order-modal-form">
            @csrf
            <input type="hidden" name="product_id" value="" data-order-modal-product-id>

            <div class="order-modal-field">
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

            <div class="order-modal-field">
                <label class="order-modal-label" for="order-modal-notes">Catatan pembelian</label>
                <textarea
                    id="order-modal-notes"
                    name="notes"
                    rows="3"
                    maxlength="255"
                    class="order-item-note-input"
                    placeholder="Contoh: tanpa gula, level pedas 2, bungkus terpisah..."
                ></textarea>
                <p class="order-modal-hint">Opsional — catatan ini akan tampil di kasir saat pembayaran.</p>
            </div>

            <button type="submit" class="btn-primary order-modal-submit">
                Tambah ke Pesanan
            </button>
        </form>
    </div>
</div>
