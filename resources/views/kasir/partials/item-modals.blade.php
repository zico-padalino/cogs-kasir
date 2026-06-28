<div class="pos-add-modal hidden" data-kasir-modal aria-hidden="true">
    <div class="pos-add-modal-backdrop" data-kasir-close-modal></div>
    <div class="pos-add-modal-panel" role="dialog" aria-modal="true" aria-labelledby="kasir-modal-title">
        <div class="pos-add-modal-head">
            <img src="" alt="" class="pos-add-modal-image" data-kasir-modal-image>
            <div class="min-w-0 flex-1">
                <h2 id="kasir-modal-title" class="pos-add-modal-title" data-kasir-modal-title></h2>
                <p class="pos-add-modal-price" data-kasir-modal-price></p>
                <p class="pos-add-modal-desc hidden" data-kasir-modal-desc></p>
            </div>
            <button type="button" class="pos-add-modal-close" data-kasir-close-modal aria-label="Tutup">×</button>
        </div>

        <form action="{{ route('kasir.items.store') }}" method="POST" class="pos-add-modal-form">
            @csrf
            <input type="hidden" name="product_id" value="" data-kasir-modal-product-id>

            <div class="pos-add-modal-field">
                <label class="pos-add-modal-label" for="kasir-modal-qty">Jumlah</label>
                <div class="order-qty-stepper">
                    <button type="button" class="order-qty-btn" data-kasir-qty-minus aria-label="Kurangi">−</button>
                    <input
                        id="kasir-modal-qty"
                        type="number"
                        name="quantity"
                        value="1"
                        min="1"
                        class="order-qty-input order-modal-qty"
                        inputmode="numeric"
                        data-kasir-modal-qty
                    >
                    <button type="button" class="order-qty-btn" data-kasir-qty-plus aria-label="Tambah">+</button>
                </div>
            </div>

            <div class="pos-add-modal-field">
                <label class="pos-add-modal-label" for="kasir-modal-notes">Catatan item</label>
                <textarea
                    id="kasir-modal-notes"
                    name="notes"
                    rows="3"
                    maxlength="255"
                    class="order-item-note-input"
                    placeholder="Contoh: tanpa gula, bungkus terpisah, level pedas 2..."
                ></textarea>
            </div>

            <button type="submit" class="btn-primary pos-add-modal-submit">Tambah ke Pesanan</button>
        </form>
    </div>
</div>

<div class="pos-detail-modal hidden" data-kasir-detail-modal aria-hidden="true">
    <div class="pos-add-modal-backdrop" data-kasir-close-detail></div>
    <div class="pos-add-modal-panel" role="dialog" aria-modal="true">
        <div class="pos-add-modal-head">
            <img src="" alt="" class="pos-add-modal-image" data-kasir-detail-image>
            <div class="min-w-0 flex-1">
                <h2 class="pos-add-modal-title" data-kasir-detail-title></h2>
                <p class="pos-add-modal-price" data-kasir-detail-price></p>
            </div>
            <button type="button" class="pos-add-modal-close" data-kasir-close-detail aria-label="Tutup">×</button>
        </div>
        <div class="pos-detail-body">
            <p class="pos-detail-text" data-kasir-detail-desc></p>
            <p class="pos-detail-meta" data-kasir-detail-meta></p>
            <a href="#" class="btn-secondary w-full text-center" data-kasir-detail-edit>Atur Gambar & Detail</a>
            <button type="button" class="btn-primary w-full" data-kasir-detail-add>Tambah ke Pesanan</button>
        </div>
    </div>
</div>
