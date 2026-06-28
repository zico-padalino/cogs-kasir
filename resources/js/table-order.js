function initOrderTableTabs() {
    const root = document.querySelector('[data-order-table]');
    if (! root) {
        return;
    }

    const tabs = root.querySelectorAll('[data-order-tab]');
    const panels = root.querySelectorAll('[data-order-panel]');

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.orderTab;

            tabs.forEach((item) => {
                item.classList.toggle('is-active', item.dataset.orderTab === target);
                item.setAttribute('aria-selected', item.dataset.orderTab === target ? 'true' : 'false');
            });

            panels.forEach((panel) => {
                const active = panel.dataset.orderPanel === target;
                panel.classList.toggle('is-active', active);

                if (window.matchMedia('(max-width: 1023px)').matches) {
                    panel.classList.toggle('hidden', ! active);
                }
            });
        });
    });
}

function initOrderSearch() {
    const input = document.querySelector('[data-order-search]');
    if (! input) {
        return;
    }

    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();

        document.querySelectorAll('[data-order-product]').forEach((card) => {
            const key = card.dataset.orderProduct || '';
            card.classList.toggle('hidden', query !== '' && ! key.includes(query));
        });
    });
}

function initOrderModal() {
    const modal = document.querySelector('[data-order-modal]');
    if (! modal) {
        return;
    }

    const title = modal.querySelector('[data-order-modal-title]');
    const price = modal.querySelector('[data-order-modal-price]');
    const image = modal.querySelector('[data-order-modal-image]');
    const productId = modal.querySelector('[data-order-modal-product-id]');
    const qtyInput = modal.querySelector('[data-order-modal-qty]');
    const notesInput = modal.querySelector('#order-modal-notes');
    const form = modal.querySelector('.order-modal-form');

    let maxQty = 99;

    const openModal = (card) => {
        maxQty = parseInt(card.dataset.productMax || '99', 10);
        productId.value = card.dataset.productId;
        title.textContent = card.dataset.productName;
        price.textContent = card.dataset.productPrice;
        image.src = card.dataset.productImage;
        image.alt = card.dataset.productName;
        qtyInput.value = '1';
        qtyInput.max = String(maxQty);
        notesInput.value = '';

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('order-modal-open');

        if (! window.matchMedia('(pointer: coarse)').matches) {
            qtyInput.focus({ preventScroll: true });
        }
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('order-modal-open');
    };

    document.querySelectorAll('[data-order-open-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            const card = button.closest('[data-order-product]');
            if (card) {
                openModal(card);
            }
        });
    });

    modal.querySelectorAll('[data-order-close-modal]').forEach((element) => {
        element.addEventListener('click', closeModal);
    });

    modal.querySelector('[data-order-qty-minus]')?.addEventListener('click', () => {
        const next = Math.max(1, parseInt(qtyInput.value || '1', 10) - 1);
        qtyInput.value = String(next);
    });

    modal.querySelector('[data-order-qty-plus]')?.addEventListener('click', () => {
        const next = Math.min(maxQty, parseInt(qtyInput.value || '1', 10) + 1);
        qtyInput.value = String(next);
    });

    form?.addEventListener('submit', () => {
        closeModal();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ! modal.classList.contains('hidden')) {
            closeModal();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initOrderTableTabs();
    initOrderSearch();
    initOrderModal();
});
