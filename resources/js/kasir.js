/**
 * Kasir POS — tab Menu/Pesanan, pencarian, modal tambah item, detail produk.
 */
const POS_DESKTOP_BP = 1024;

function readProductCard(card) {
    return {
        id: card.dataset.productId,
        name: card.dataset.productName,
        sku: card.dataset.productSku,
        price: card.dataset.productPrice,
        priceValue: parseFloat(card.dataset.productPriceValue || '0'),
        image: card.dataset.productImage,
        desc: card.dataset.productDesc || '',
        stock: card.dataset.productStock || '0',
        max: parseInt(card.dataset.productMax || '99', 10),
        editUrl: card.dataset.productEditUrl || '#',
    };
}

function initKasirModals(root) {
    const addModal = root.querySelector('[data-kasir-modal]');
    const detailModal = root.querySelector('[data-kasir-detail-modal]');

    if (! addModal || ! detailModal) {
        return;
    }

    const addForm = addModal.querySelector('.pos-add-modal-form');
    const addProductId = addModal.querySelector('[data-kasir-modal-product-id]');
    const addTitle = addModal.querySelector('[data-kasir-modal-title]');
    const addPrice = addModal.querySelector('[data-kasir-modal-price]');
    const addDesc = addModal.querySelector('[data-kasir-modal-desc]');
    const addImage = addModal.querySelector('[data-kasir-modal-image]');
    const addQty = addModal.querySelector('[data-kasir-modal-qty]');
    const addNotes = addModal.querySelector('#kasir-modal-notes');

    const detailTitle = detailModal.querySelector('[data-kasir-detail-title]');
    const detailPrice = detailModal.querySelector('[data-kasir-detail-price]');
    const detailDesc = detailModal.querySelector('[data-kasir-detail-desc]');
    const detailMeta = detailModal.querySelector('[data-kasir-detail-meta]');
    const detailImage = detailModal.querySelector('[data-kasir-detail-image]');
    const detailEdit = detailModal.querySelector('[data-kasir-detail-edit]');
    const detailAdd = detailModal.querySelector('[data-kasir-detail-add]');

    let activeProduct = null;
    let maxQty = 99;

    const openAddModal = (product) => {
        activeProduct = product;
        maxQty = product.max;

        addProductId.value = product.id;
        addTitle.textContent = product.name;
        addPrice.textContent = product.price;
        addImage.src = product.image;
        addImage.alt = product.name;
        addQty.value = '1';
        addQty.max = String(maxQty);
        addNotes.value = '';

        if (product.desc && product.desc !== 'Belum ada deskripsi menu.') {
            addDesc.textContent = product.desc;
            addDesc.classList.remove('hidden');
        } else {
            addDesc.textContent = '';
            addDesc.classList.add('hidden');
        }

        addModal.classList.remove('hidden');
        addModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('pos-modal-open');
        addQty.focus();
    };

    const closeAddModal = () => {
        addModal.classList.add('hidden');
        addModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('pos-modal-open');
    };

    const openDetailModal = (product) => {
        activeProduct = product;

        detailTitle.textContent = product.name;
        detailPrice.textContent = product.price;
        detailDesc.textContent = product.desc;
        detailMeta.textContent = `${product.sku} · Stok ${product.stock}`;
        detailImage.src = product.image;
        detailImage.alt = product.name;
        detailEdit.href = product.editUrl;
        detailAdd.disabled = product.priceValue <= 0;

        detailModal.classList.remove('hidden');
        detailModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('pos-modal-open');
    };

    const closeDetailModal = () => {
        detailModal.classList.add('hidden');
        detailModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('pos-modal-open');
    };

    root.querySelectorAll('[data-kasir-open-add]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const card = button.closest('[data-kasir-product]');
            if (card) {
                openAddModal(readProductCard(card));
            }
        });
    });

    root.querySelectorAll('[data-kasir-open-detail]').forEach((button) => {
        button.addEventListener('click', () => {
            const card = button.closest('[data-kasir-product]');
            if (card) {
                openDetailModal(readProductCard(card));
            }
        });
    });

    addModal.querySelectorAll('[data-kasir-close-modal]').forEach((el) => {
        el.addEventListener('click', closeAddModal);
    });

    detailModal.querySelectorAll('[data-kasir-close-detail]').forEach((el) => {
        el.addEventListener('click', closeDetailModal);
    });

    addModal.querySelector('[data-kasir-qty-minus]')?.addEventListener('click', () => {
        const next = Math.max(1, parseInt(addQty.value || '1', 10) - 1);
        addQty.value = String(next);
    });

    addModal.querySelector('[data-kasir-qty-plus]')?.addEventListener('click', () => {
        const next = Math.min(maxQty, parseInt(addQty.value || '1', 10) + 1);
        addQty.value = String(next);
    });

    detailAdd?.addEventListener('click', () => {
        if (activeProduct) {
            closeDetailModal();
            openAddModal(activeProduct);
        }
    });

    addForm?.addEventListener('submit', closeAddModal);

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        if (! addModal.classList.contains('hidden')) {
            closeAddModal();
        }

        if (! detailModal.classList.contains('hidden')) {
            closeDetailModal();
        }
    });
}

export function initKasirPos() {
    const root = document.getElementById('kasir-pos');
    if (! root) {
        return;
    }

    initKasirModals(root);

    const tabs = root.querySelectorAll('[data-kasir-tab]');
    const panels = root.querySelectorAll('[data-kasir-panel]');
    const cartCount = root.querySelector('[data-kasir-cart-count]');
    const searchInput = root.querySelector('[data-kasir-search]');

    const setPanel = (name) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.kasirTab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            const show = panel.dataset.kasirPanel === name;
            panel.classList.toggle('hidden', !show);
            panel.classList.toggle('flex', show);
        });

        if (window.innerWidth < POS_DESKTOP_BP && name === 'cart') {
            root.querySelector('.pos-receipt-body')?.scrollTo({ top: 0, behavior: 'smooth' });
        }
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => setPanel(tab.dataset.kasirTab));
    });

    root.querySelectorAll('[data-kasir-go-cart]').forEach((btn) => {
        btn.addEventListener('click', () => setPanel('cart'));
    });

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();

            root.querySelectorAll('[data-kasir-product]').forEach((tile) => {
                const key = tile.dataset.kasirProduct ?? '';
                const match = query === '' || key.includes(query);
                tile.classList.toggle('hidden', !match);
            });
        });
    }

    root.querySelectorAll('.pos-pay-option input[type="radio"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            root.querySelectorAll('.pos-pay-option').forEach((option) => {
                const input = option.querySelector('input[type="radio"]');
                option.classList.toggle('is-selected', Boolean(input?.checked));
            });
        });
    });

    const updateCartBadge = () => {
        const count = root.querySelectorAll('[data-kasir-item]').length;
        if (cartCount) {
            cartCount.textContent = String(count);
            cartCount.classList.toggle('hidden', count === 0);
        }
    };

    updateCartBadge();

    const syncLayout = () => {
        if (window.innerWidth >= POS_DESKTOP_BP) {
            panels.forEach((panel) => {
                panel.classList.remove('hidden');
                panel.classList.add('flex');
            });
            return;
        }

        const activeTab = root.querySelector('[data-kasir-tab].is-active');
        setPanel(activeTab?.dataset.kasirTab ?? 'menu');
    };

    window.addEventListener('resize', syncLayout);
    syncLayout();
}

document.addEventListener('DOMContentLoaded', initKasirPos);
