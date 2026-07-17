function initOrderCheckoutTypeCards() {
    const form = document.querySelector('.order-checkout-form');
    if (! form) {
        return;
    }

    const cards = form.querySelectorAll('.order-type-card');
    const sync = () => {
        cards.forEach((card) => {
            const input = card.querySelector('input[type="radio"]');
            card.classList.toggle('is-active', Boolean(input?.checked));
        });
    };

    cards.forEach((card) => {
        card.addEventListener('change', sync);
    });

    sync();
}

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

function formatIdr(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount).replace(/\s/g, ' ');
}

function parseCardAddons(card) {
    try {
        const parsed = JSON.parse(card.dataset.productAddons || '[]');

        return Array.isArray(parsed) ? parsed : [];
    } catch (_) {
        return [];
    }
}

function initOrderModal() {
    const modal = document.querySelector('[data-order-modal]');
    if (! modal) {
        return;
    }

    const title = modal.querySelector('[data-order-modal-title]');
    const price = modal.querySelector('[data-order-modal-price]');
    const image = modal.querySelector('[data-order-modal-image]');
    const desc = modal.querySelector('[data-order-modal-desc]');
    const productId = modal.querySelector('[data-order-modal-product-id]');
    const qtyInput = modal.querySelector('[data-order-modal-qty]');
    const notesInput = modal.querySelector('#order-modal-notes');
    const addonsWrap = modal.querySelector('[data-order-addons-wrap]');
    const addonsBox = modal.querySelector('[data-order-addons]');
    const unavailable = modal.querySelector('[data-order-unavailable]');
    const canAddFields = modal.querySelectorAll('[data-order-can-add-only]');
    const form = modal.querySelector('[data-order-modal-form]');

    let maxQty = 99;
    let basePrice = 0;

    const selectedAddonExtra = () => {
        if (! addonsBox) {
            return 0;
        }

        return Array.from(addonsBox.querySelectorAll('input[type="checkbox"]:checked'))
            .reduce((sum, input) => sum + (parseFloat(input.dataset.addonPrice || '0') || 0), 0);
    };

    const refreshPrice = () => {
        if (! price) {
            return;
        }

        price.textContent = formatIdr(basePrice + selectedAddonExtra());
    };

    const setCanAdd = (canAdd) => {
        canAddFields.forEach((el) => {
            el.classList.toggle('hidden', ! canAdd);
            if (el.matches('button, input, textarea, select')) {
                el.disabled = ! canAdd;
            }
            el.querySelectorAll('input, textarea, select, button').forEach((field) => {
                field.disabled = ! canAdd;
            });
        });

        unavailable?.classList.toggle('hidden', canAdd);
    };

    const renderAddons = (addons) => {
        if (! addonsWrap || ! addonsBox) {
            return;
        }

        addonsBox.innerHTML = '';

        if (! addons.length) {
            addonsWrap.classList.add('hidden');

            return;
        }

        addons.forEach((addon) => {
            const label = document.createElement('label');
            label.className = 'pos-addon-item';

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'addon_ids[]';
            input.value = String(addon.id);
            input.dataset.addonPrice = String(addon.price || 0);
            input.addEventListener('change', refreshPrice);

            const name = document.createElement('span');
            name.className = 'pos-addon-item-name';
            name.textContent = addon.name;

            const priceEl = document.createElement('span');
            priceEl.className = 'pos-addon-item-price';
            priceEl.textContent = addon.price_label || formatIdr(addon.price || 0);

            label.appendChild(input);
            label.appendChild(name);
            label.appendChild(priceEl);
            addonsBox.appendChild(label);
        });

        addonsWrap.classList.remove('hidden');
    };

    const openModal = (card) => {
        const canAdd = card.dataset.productCanAdd !== '0';
        maxQty = parseInt(card.dataset.productMax || '99', 10);
        basePrice = parseFloat(card.dataset.productPriceValue || '0') || 0;
        productId.value = card.dataset.productId || '';
        title.textContent = card.dataset.productName || '';
        image.src = card.dataset.productImage || '';
        image.alt = card.dataset.productName || '';
        if (desc) {
            desc.textContent = card.dataset.productDesc || 'Belum ada deskripsi menu.';
        }
        qtyInput.value = '1';
        qtyInput.max = String(maxQty);
        notesInput.value = '';
        setCanAdd(canAdd);
        renderAddons(canAdd ? parseCardAddons(card) : []);
        refreshPrice();

        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('order-modal-open');

        if (canAdd && ! window.matchMedia('(pointer: coarse)').matches) {
            qtyInput.focus({ preventScroll: true });
        }
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('order-modal-open');
    };

    document.querySelectorAll('[data-order-open-modal]').forEach((trigger) => {
        const openFromTrigger = () => {
            const card = trigger.closest('[data-order-product]') || trigger;
            if (card) {
                openModal(card);
            }
        };

        trigger.addEventListener('click', openFromTrigger);
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openFromTrigger();
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

    form?.addEventListener('submit', (event) => {
        const submit = form.querySelector('.order-modal-submit');
        if (submit?.classList.contains('hidden') || submit?.disabled) {
            event.preventDefault();

            return;
        }

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
    initOrderCheckoutTypeCards();
    initOrderKasirConfirmation();
});

function initOrderKasirConfirmation() {
    const section = document.querySelector('[data-order-waiting-kasir]');
    if (! section) {
        return;
    }

    if (window.location.hash === '#ke-kasir') {
        window.requestAnimationFrame(() => {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    const statusUrl = section.dataset.orderStatusUrl;
    if (! statusUrl) {
        return;
    }

    const initialStatus = section.dataset.orderInitialStatus || '';

    const poll = async () => {
        try {
            const response = await fetch(statusUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (! response.ok) {
                return;
            }

            const data = await response.json();

            if (data.is_paid || (initialStatus && data.status !== initialStatus)) {
                window.location.reload();
            }
        } catch {
            // ignore transient network errors
        }
    };

    window.setInterval(poll, 5000);
    poll();
}
