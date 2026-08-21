/**
 * Kasir POS — tab Menu/Pesanan, pencarian, modal tambah item, detail produk.
 */
import { formatRupiahInput, formatRupiahInputLive, parseRupiahInput } from './rupiah';

const POS_DESKTOP_BP = 1024;

function clearKasirCustomerNameError(root) {
    const field = root.querySelector('[data-pos-customer-field]');
    const error = root.querySelector('[data-pos-customer-error]');
    field?.classList.remove('is-invalid');
    error?.classList.add('hidden');
}

function clearKasirOrderTypeError(root) {
    const error = root.querySelector('[data-pos-order-type-error]');
    error?.classList.add('hidden');
}

function expandKasirOrderBar(root) {
    const bar = root.querySelector('[data-pos-order-bar]');
    const backdrop = root.querySelector('[data-pos-order-bar-backdrop]');
    const toggle = root.querySelector('[data-pos-order-bar-toggle]');

    if (bar) {
        bar.classList.add('is-expanded');
    }

    if (window.innerWidth < POS_DESKTOP_BP) {
        root.classList.add('is-order-bar-open');
        backdrop?.classList.remove('hidden');
        backdrop?.setAttribute('aria-hidden', 'false');
    }

    toggle?.setAttribute('aria-expanded', 'true');
}

function promptKasirCustomerName(root) {
    const field = root.querySelector('[data-pos-customer-field]');
    const input = root.querySelector('[data-pos-customer-note]');
    const error = root.querySelector('[data-pos-customer-error]');

    expandKasirOrderBar(root);
    field?.classList.add('is-invalid');
    error?.classList.remove('hidden');

    window.requestAnimationFrame(() => {
        input?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        input?.focus();
        input?.select?.();
    });
}

function promptKasirOrderType(root) {
    const error = root.querySelector('[data-pos-order-type-error]');
    const group = root.querySelector('[data-pos-order-type-group]');

    expandKasirOrderBar(root);
    error?.classList.remove('hidden');

    window.requestAnimationFrame(() => {
        group?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    });
}

async function ensureKasirCustomerName(root) {
    if (root.dataset.kasirRequireCustomer !== '1') {
        return true;
    }

    const input = root.querySelector('[data-pos-customer-note]');
    const name = (input?.value || '').trim();

    if (! name) {
        promptKasirCustomerName(root);

        return false;
    }

    clearKasirCustomerNameError(root);

    // Pastikan nama sudah tersimpan ke server sebelum add item.
    if (typeof root.__kasirFlushOrderBar === 'function') {
        try {
            await root.__kasirFlushOrderBar();
        } catch {
            promptKasirCustomerName(root);

            return false;
        }
    }

    return true;
}

async function ensureKasirOrderType(root) {
    if (root.dataset.kasirRequireCustomer !== '1') {
        return true;
    }

    const checked = root.querySelector('[data-pos-order-type]:checked');
    if (! checked?.value) {
        promptKasirOrderType(root);

        return false;
    }

    clearKasirOrderTypeError(root);

    return true;
}

async function ensureKasirOrderContext(root) {
    if (! await ensureKasirOrderType(root)) {
        return false;
    }

    return ensureKasirCustomerName(root);
}

/**
 * Safari/Chrome mobile: toolbar browser menutupi bottom dock.
 * Tinggi layout mengikuti area yang benar-benar terlihat (visualViewport).
 */
function syncBrowserViewportChrome() {
    const root = document.documentElement;
    const vv = window.visualViewport;

    if (window.innerWidth >= POS_DESKTOP_BP) {
        root.style.removeProperty('--vvh');
        return;
    }

    if (! vv) {
        root.style.setProperty('--vvh', `${window.innerHeight}px`);
        return;
    }

    // Tinggi area visible; jangan pakai innerHeight (masih include toolbar)
    const visibleHeight = Math.max(240, Math.round(vv.height));
    const pickerState = window.__cogsVvh || { lastGood: 0, pickerUntil: 0 };
    window.__cogsVvh = pickerState;

    if (
        document.visibilityState !== 'hidden'
        && Date.now() < pickerState.pickerUntil
        && pickerState.lastGood > 0
        && visibleHeight < pickerState.lastGood * 0.75
    ) {
        return;
    }

    pickerState.lastGood = visibleHeight;
    root.style.setProperty('--vvh', `${visibleHeight}px`);
}

function initBrowserViewportChrome() {
    let frame = 0;
    const sync = () => {
        if (frame) {
            return;
        }

        frame = window.requestAnimationFrame(() => {
            frame = 0;
            syncBrowserViewportChrome();
        });
    };

    syncBrowserViewportChrome();
    window.addEventListener('resize', sync);
    window.addEventListener('orientationchange', () => {
        window.setTimeout(syncBrowserViewportChrome, 100);
        window.setTimeout(syncBrowserViewportChrome, 350);
    });

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', sync);
        window.visualViewport.addEventListener('scroll', sync);
    }
}

function readProductCard(card) {
    let addons = [];

    try {
        addons = JSON.parse(card.dataset.productAddons || '[]');
    } catch (_) {
        addons = [];
    }

    return {
        id: card.dataset.productId,
        name: card.dataset.productName,
        sku: card.dataset.productSku,
        price: card.dataset.productPrice,
        priceValue: parseFloat(card.dataset.productPriceValue || '0'),
        image: card.dataset.productImage,
        desc: card.dataset.productDesc || '',
        editUrl: card.dataset.productEditUrl || '#',
        addons: Array.isArray(addons) ? addons : [],
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
    const addonsWrap = addModal.querySelector('[data-kasir-addons-wrap]');
    const addonsBox = addModal.querySelector('[data-kasir-addons]');

    const detailTitle = detailModal.querySelector('[data-kasir-detail-title]');
    const detailPrice = detailModal.querySelector('[data-kasir-detail-price]');
    const detailDesc = detailModal.querySelector('[data-kasir-detail-desc]');
    const detailMeta = detailModal.querySelector('[data-kasir-detail-meta]');
    const detailImage = detailModal.querySelector('[data-kasir-detail-image]');
    const detailEdit = detailModal.querySelector('[data-kasir-detail-edit]');
    const detailAdd = detailModal.querySelector('[data-kasir-detail-add]');

    let activeProduct = null;
    const maxQty = 99;

    const formatIdr = (amount) => new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount).replace(/\s/g, ' ');

    const selectedAddonExtra = () => {
        if (! addonsBox) {
            return 0;
        }

        return Array.from(addonsBox.querySelectorAll('input[type="checkbox"]:checked'))
            .reduce((sum, input) => sum + (parseFloat(input.dataset.addonPrice || '0') || 0), 0);
    };

    const refreshAddModalPrice = () => {
        if (! activeProduct || ! addPrice) {
            return;
        }

        const total = (activeProduct.priceValue || 0) + selectedAddonExtra();
        addPrice.textContent = formatIdr(total);
    };

    const renderAddons = (product) => {
        if (! addonsWrap || ! addonsBox) {
            return;
        }

        addonsBox.innerHTML = '';
        const list = product.addons || [];

        if (list.length === 0) {
            addonsWrap.classList.add('hidden');

            return;
        }

        list.forEach((addon) => {
            const label = document.createElement('label');
            label.className = 'pos-addon-item';

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'addon_ids[]';
            input.value = String(addon.id);
            input.dataset.addonPrice = String(addon.price || 0);
            input.addEventListener('change', refreshAddModalPrice);

            const text = document.createElement('span');
            text.className = 'pos-addon-item-name';
            text.textContent = addon.name;

            const price = document.createElement('span');
            price.className = 'pos-addon-item-price';
            price.textContent = addon.price_label || formatIdr(addon.price || 0);

            label.appendChild(input);
            label.appendChild(text);
            label.appendChild(price);
            addonsBox.appendChild(label);
        });

        addonsWrap.classList.remove('hidden');
    };

    const openAddModal = (product) => {
        if (! product || ! product.id || product.priceValue <= 0) {
            return;
        }

        activeProduct = product;

        addProductId.value = product.id;
        addTitle.textContent = product.name;
        addImage.src = product.image;
        addImage.alt = product.name;
        addQty.value = '1';
        addQty.max = String(maxQty);
        addNotes.value = '';
        renderAddons(product);
        refreshAddModalPrice();

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
        window.setTimeout(() => addQty?.focus(), 50);
    };

    const tryOpenAddModal = async (product) => {
        if (! await ensureKasirOrderContext(root)) {
            return;
        }

        openAddModal(product);
    };

    const closeAddModal = () => {
        addModal.classList.add('hidden');
        addModal.setAttribute('aria-hidden', 'true');
        if (detailModal.classList.contains('hidden')) {
            document.body.classList.remove('pos-modal-open');
        }
    };

    const openDetailModal = (product) => {
        activeProduct = product;

        detailTitle.textContent = product.name;
        detailPrice.textContent = product.price;
        detailDesc.textContent = product.desc;
        detailMeta.textContent = product.sku;
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
        if (addModal.classList.contains('hidden')) {
            document.body.classList.remove('pos-modal-open');
        }
    };

    root.addEventListener('click', (event) => {
        const addTrigger = event.target.closest('[data-kasir-open-add]');
        if (addTrigger && root.contains(addTrigger)) {
            event.preventDefault();
            event.stopPropagation();

            if (addTrigger.disabled || addTrigger.getAttribute('aria-disabled') === 'true') {
                return;
            }

            const card = addTrigger.closest('[data-kasir-product]');
            if (card) {
                void tryOpenAddModal(readProductCard(card));
            }

            return;
        }

        const detailTrigger = event.target.closest('[data-kasir-open-detail]');
        if (detailTrigger && root.contains(detailTrigger)) {
            event.preventDefault();
            const card = detailTrigger.closest('[data-kasir-product]');
            if (card) {
                openDetailModal(readProductCard(card));
            }
        }
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
            void tryOpenAddModal(activeProduct);
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

function parseDeliverItems(raw) {
    if (raw == null) {
        return [];
    }

    const text = String(raw).trim();
    if (! text) {
        return [];
    }

    const tryParse = (value) => {
        try {
            const parsed = JSON.parse(value);
            return Array.isArray(parsed) ? parsed : null;
        } catch (_) {
            return null;
        }
    };

    const direct = tryParse(text);
    if (direct) {
        return direct;
    }

    // Fallback: JSON diatribut ter-escape HTML entity
    if (text.includes('&')) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = text;
        const decoded = tryParse(textarea.value);
        if (decoded) {
            return decoded;
        }
    }

    // Fallback: base64
    try {
        const decoded = tryParse(atob(text));
        if (decoded) {
            return decoded;
        }
    } catch (_) {
        // ignore
    }

    return [];
}

function readDeliverItemsFromButton(btn) {
    if (! btn) {
        return [];
    }

    const payload = btn.querySelector('[data-deliver-payload]');
    if (payload) {
        const fromPayload = parseDeliverItems(payload.textContent);
        if (fromPayload.length > 0) {
            return fromPayload;
        }
    }

    const attr = btn.getAttribute('data-deliver-items');
    const fromAttr = parseDeliverItems(attr);
    if (fromAttr.length > 0) {
        return fromAttr;
    }

    return parseDeliverItems(btn.dataset.deliverItems);
}

function writeDeliverItemsToButton(btn, nextItems) {
    if (! btn) {
        return;
    }

    const encoded = JSON.stringify(nextItems);
    const payload = btn.querySelector('[data-deliver-payload]');
    if (payload) {
        payload.textContent = encoded;
    }
    btn.setAttribute('data-deliver-items', encoded);
    btn.dataset.deliverItems = encoded;
}

function escapeDeliverHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function initDeliverModal() {
    const modal = document.querySelector('[data-kasir-deliver-modal]');
    if (! modal) {
        return;
    }

    // Pindahkan ke body supaya tidak ketutup overflow POS shell.
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    if (modal.dataset.boundDeliverModal === '1') {
        return;
    }
    modal.dataset.boundDeliverModal = '1';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const listEl = modal.querySelector('[data-deliver-modal-list]');
    const titleEl = modal.querySelector('[data-deliver-modal-title]');
    const progressEl = modal.querySelector('[data-deliver-modal-progress]');
    let activeOpenBtn = null;
    let items = [];

    const setOpen = (open) => {
        if (open) {
            openKasirOverlay(modal);
            document.body.classList.add('overflow-hidden');
        } else {
            closeKasirOverlay(modal);
            document.body.classList.remove('overflow-hidden');
        }
    };

    const updateCardDeliverProgress = (btn, doneCount, totalCount) => {
        if (! btn) {
            return;
        }

        const doneEl = btn.querySelector('[data-deliver-done]');
        const totalEl = btn.querySelector('[data-deliver-total]');
        if (doneEl) doneEl.textContent = String(doneCount);
        if (totalEl) totalEl.textContent = String(totalCount);

        const card = btn.closest('.pos-pending-card');
        const progressText = card?.querySelector('[data-pending-deliver-progress], .pos-pending-deliver');
        if (progressText) {
            progressText.textContent = `Diantar ${doneCount}/${totalCount}`;
        }
    };

    const syncProgress = () => {
        const done = items.filter((item) => item.is_delivered).length;
        const total = items.length;
        if (progressEl) {
            progressEl.textContent = `Diantar ${done}/${total}`;
        }
        if (activeOpenBtn) {
            // Tombol bisa sudah diganti polling — cari ulang di DOM.
            if (! document.body.contains(activeOpenBtn)) {
                const match = [...document.querySelectorAll('[data-deliver-open]')].find((btn) => {
                    const btnItems = readDeliverItemsFromButton(btn);
                    return btnItems.some((row) => items.some((item) => Number(item.id) === Number(row.id)));
                });
                if (match) {
                    activeOpenBtn = match;
                }
            }
            writeDeliverItemsToButton(activeOpenBtn, items);
            updateCardDeliverProgress(activeOpenBtn, done, total);
        }
        document.querySelectorAll('[data-deliver-open]').forEach((btn) => {
            if (btn === activeOpenBtn) return;
            const btnItems = readDeliverItemsFromButton(btn);
            if (btnItems.length === 0) return;
            const same = btnItems.some((row) => items.some((item) => Number(item.id) === Number(row.id)));
            if (! same) return;
            const merged = btnItems.map((row) => {
                const next = items.find((item) => Number(item.id) === Number(row.id));
                return next ? { ...row, is_delivered: next.is_delivered } : row;
            });
            writeDeliverItemsToButton(btn, merged);
            const d = merged.filter((row) => row.is_delivered).length;
            updateCardDeliverProgress(btn, d, merged.length);
        });
        items.forEach((item) => {
            document.querySelectorAll(`[data-order-item-row][data-item-id="${item.id}"]`).forEach((row) => {
                row.classList.toggle('is-delivered', Boolean(item.is_delivered));
            });
        });
    };

    const renderList = () => {
        if (! listEl) return;
        if (items.length === 0) {
            listEl.innerHTML = '<p class="pos-deliver-modal-empty">Tidak ada item.</p>';
            syncProgress();
            return;
        }

        listEl.innerHTML = items.map((item) => `
            <label class="pos-deliver-modal-row ${item.is_delivered ? 'is-delivered' : ''}" data-deliver-row data-item-id="${escapeDeliverHtml(item.id)}">
                <input
                    type="checkbox"
                    class="pos-deliver-checkbox"
                    data-deliver-toggle
                    data-url="${escapeDeliverHtml(item.url || '')}"
                    data-item-id="${escapeDeliverHtml(item.id)}"
                    ${item.is_delivered ? 'checked' : ''}
                    aria-label="Sudah diantar: ${escapeDeliverHtml(item.name || 'Item')}"
                >
                <span class="pos-deliver-modal-row-body">
                    <span class="pos-deliver-modal-row-name">${escapeDeliverHtml(item.name || 'Item')}</span>
                    <span class="pos-deliver-modal-row-meta">Qty ${escapeDeliverHtml(item.qty ?? 1)}</span>
                </span>
            </label>
        `).join('');

        syncProgress();

        listEl.querySelectorAll('[data-deliver-toggle]').forEach((input) => {
            input.addEventListener('change', async () => {
                const url = input.getAttribute('data-url');
                const itemId = Number(input.getAttribute('data-item-id'));
                const next = Boolean(input.checked);
                const row = input.closest('[data-deliver-row]');
                if (! url) {
                    input.checked = ! next;
                    return;
                }
                input.disabled = true;
                try {
                    const res = await fetch(url, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrf || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ is_delivered: next }),
                    });
                    const payload = await res.json().catch(() => ({}));
                    if (! res.ok) {
                        input.checked = ! next;
                        window.alert(payload.message || 'Gagal menyimpan ceklis.');
                        return;
                    }
                    items = items.map((item) => (
                        Number(item.id) === itemId ? { ...item, is_delivered: next } : item
                    ));
                    row?.classList.toggle('is-delivered', next);
                    syncProgress();
                    if (payload.data?.status_label) {
                        const badge = document.querySelector('[data-order-status-badge]');
                        if (badge) badge.textContent = payload.data.status_label;
                    }
                } catch (_) {
                    input.checked = ! next;
                    window.alert('Gagal menyimpan ceklis.');
                } finally {
                    input.disabled = false;
                }
            });
        });
    };

    const openWith = (btn) => {
        activeOpenBtn = btn;
        items = readDeliverItemsFromButton(btn);
        if (titleEl) {
            titleEl.textContent = btn.dataset.deliverTitle || 'Item pesanan';
        }
        renderList();
        setOpen(true);
    };

    modal.querySelectorAll('[data-kasir-close-deliver]').forEach((el) => {
        el.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ! modal.classList.contains('hidden')) {
            setOpen(false);
        }
    });

    // Delegation: tetap jalan setelah polling ganti HTML antrian.
    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-deliver-open]');
        if (! btn) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        openWith(btn);
    });
}

export function initItemDeliverToggle() {
    initDeliverModal();
}

export function initKasirPos() {
    const root = document.getElementById('kasir-pos');
    if (! root) {
        return;
    }

    initBrowserViewportChrome();
    initKasirModals(root);
    initPosCategoryTabs(root);
    initPosOrderBar(root);
    initPosDiscount(root);
    initPosCashPayment(root);
    initPosPayModal(root);
    initPosPendingPanel(root);
    initPosFlash(root);
    initItemDeliverToggle(root);

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

        syncMobilePayChrome(root, name);

        if (window.innerWidth < POS_DESKTOP_BP && name === 'cart') {
            window.scrollTo(0, 0);
            root.querySelector('.pos-receipt-body')?.scrollTo?.(0, 0);
        }
    };

    kasirSetPanel = setPanel;

    const scrollToPayDock = (scope) => {
        const pay = scope.querySelector('[data-pos-receipt-pay], [data-pos-receipt-confirm]');
        if (! pay) {
            return;
        }

        window.requestAnimationFrame(() => {
            pay.scrollIntoView({ block: 'end', behavior: 'smooth', inline: 'nearest' });
        });
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => setPanel(tab.dataset.kasirTab));
    });

    root.querySelectorAll('[data-kasir-go-menu]').forEach((btn) => {
        if (btn.dataset.boundGoMenu === '1') {
            return;
        }
        btn.dataset.boundGoMenu = '1';
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            setPanel('menu');
        });
    });

    root.querySelectorAll('[data-kasir-go-cart]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            // Dock selalu buka tab keranjang dulu (cek item / diskon / simpan),
            // supaya "Bayar" tidak loncat langsung ke modal.
            const activeTab = root.querySelector('[data-kasir-tab].is-active')?.dataset.kasirTab;

            if (activeTab !== 'cart') {
                setPanel('cart');
                return;
            }

            scrollToPayDock(root);
        });
    });

    root.querySelectorAll('[data-kasir-open-pay]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const payModal = root.querySelector('[data-kasir-pay-modal]');
            if (payModal) {
                preparePosPayModal(payModal, root);
                openKasirOverlay(payModal);
            }
        });
    });

    root.querySelectorAll('[data-kasir-open-confirm]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const confirmModal = root.querySelector('[data-kasir-confirm-modal]');
            if (confirmModal) {
                openKasirOverlay(confirmModal);
            }
        });
    });

    if (searchInput) {
        const setSearchFocused = (on) => {
            if (window.innerWidth >= POS_DESKTOP_BP) {
                document.body.classList.remove('is-menu-search-focused');

                return;
            }

            document.body.classList.toggle('is-menu-search-focused', on);

            if (on) {
                window.requestAnimationFrame(() => {
                    searchInput.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                });
                window.setTimeout(() => {
                    searchInput.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }, 280);
            }
        };

        searchInput.addEventListener('focus', () => setSearchFocused(true));
        searchInput.addEventListener('blur', () => {
            window.setTimeout(() => setSearchFocused(false), 120);
        });

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();

            root.querySelectorAll('[data-kasir-product]').forEach((tile) => {
                const key = tile.dataset.kasirProduct ?? '';
                const match = query === '' || key.includes(query);
                tile.classList.toggle('hidden', !match);
            });
        });
    }

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
            root.classList.remove('is-mobile-cart-tab', 'is-mobile-menu-tab');
            document.body.classList.remove('is-mobile-cart-tab', 'is-mobile-menu-tab');
            root.querySelector('[data-pos-mobile-checkout]')?.classList.remove('hidden');

            return;
        }

        const activeTab = root.querySelector('[data-kasir-tab].is-active');
        const tabName = activeTab?.dataset.kasirTab ?? 'menu';
        setPanel(tabName);
    };

    window.addEventListener('resize', syncLayout);
    syncLayout();
    syncMobilePayChrome(root, root.querySelector('[data-kasir-tab].is-active')?.dataset.kasirTab ?? 'menu');
}

function syncMobilePayChrome(root, activeTab) {
    const isMobile = window.innerWidth < POS_DESKTOP_BP;
    const mobileCheckout = root.querySelector('[data-pos-mobile-checkout]');
    const goCartLabel = root.querySelector('[data-kasir-go-cart-label]');
    const needsConfirm = Boolean(
        root.querySelector('[data-pos-receipt-confirm], [data-kasir-confirm-modal]')
    );

    root.classList.toggle('is-mobile-cart-tab', isMobile && activeTab === 'cart');
    root.classList.toggle('is-mobile-menu-tab', isMobile && activeTab === 'menu');
    document.body.classList.toggle('is-mobile-cart-tab', isMobile && activeTab === 'cart');
    document.body.classList.toggle('is-mobile-menu-tab', isMobile && activeTab === 'menu');

    const themeMeta = document.querySelector('meta[name="theme-color"]');
    if (themeMeta) {
        themeMeta.setAttribute('content', isMobile && activeTab === 'cart' ? '#1c1410' : '#5c4033');
    }

    if (mobileCheckout) {
        mobileCheckout.classList.toggle('hidden', ! isMobile || activeTab === 'cart');
    }

    if (goCartLabel) {
        goCartLabel.textContent = needsConfirm ? 'Lihat & konfirmasi' : 'Lihat pesanan';
    }
}

function initPosCategoryTabs(root) {
    const tabs = root.querySelectorAll('[data-kasir-category]');
    if (tabs.length === 0) {
        return;
    }

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            tabs.forEach((item) => item.classList.toggle('is-active', item === tab));

            const category = tab.dataset.kasirCategory;

            root.querySelectorAll('[data-menu-category]').forEach((card) => {
                const match = category === 'all' || card.dataset.menuCategory === category;
                card.classList.toggle('hidden', ! match);
            });
        });
    });
}

function initPosOrderBar(root) {
    const bar = root.querySelector('[data-pos-order-bar]');
    if (! bar) {
        return;
    }

    const customerInput = bar.querySelector('[data-pos-customer-note]');
    const saveStatus = bar.querySelector('[data-pos-save-status]');
    const typeCards = bar.querySelectorAll('[data-pos-order-type-card]');
    const typeRadios = bar.querySelectorAll('[data-pos-order-type]');
    const orderSummary = bar.querySelector('[data-pos-order-summary]');
    const orderBarToggle = bar.querySelector('[data-pos-order-bar-toggle]');
    const orderBarBackdrop = root.querySelector('[data-pos-order-bar-backdrop]');

    const toolbarType = root.querySelector('[data-pos-toolbar-type]');
    const toolbarCustomer = root.querySelector('[data-pos-toolbar-customer]');
    const receiptContext = root.querySelector('[data-pos-receipt-context]');

    let saveTimer = null;
    let saving = false;

    const setOrderBarExpanded = (expanded) => {
        const isMobile = window.innerWidth < POS_DESKTOP_BP;

        bar.classList.toggle('is-expanded', expanded);
        root.classList.toggle('is-order-bar-open', isMobile && expanded);

        if (orderBarToggle) {
            orderBarToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }

        if (orderBarBackdrop) {
            orderBarBackdrop.classList.toggle('hidden', ! expanded || ! isMobile);
            orderBarBackdrop.setAttribute('aria-hidden', expanded && isMobile ? 'false' : 'true');
        }
    };

    const collapseOrderBarOnMobile = () => {
        if (bar.classList.contains('is-expanded')) {
            setOrderBarExpanded(false);
        }
    };

    const buildOrderSummary = (data) => {
        const parts = [];

        if (data.order_type_label) {
            parts.push(`${data.order_type_icon ?? ''} ${data.order_type_label}`.trim());
        }

        if (data.customer_note) {
            parts.push(data.customer_note);
        }

        return parts.length > 0 ? parts.join(' · ') : 'Atur tipe pesanan';
    };

    const updateOrderSummary = (data) => {
        if (orderSummary) {
            orderSummary.textContent = buildOrderSummary(data);
        }
    };

    const setSaveStatus = (state, message) => {
        if (! saveStatus) {
            return;
        }

        saveStatus.classList.remove('hidden', 'is-saving', 'is-success', 'is-error');
        saveStatus.classList.add(state === 'saving' ? 'is-saving' : state === 'error' ? 'is-error' : 'is-success');
        saveStatus.textContent = message;
    };

    const clearSaveStatus = () => {
        saveStatus?.classList.add('hidden');
    };

    const syncTypeCards = () => {
        const type = bar.querySelector('[data-pos-order-type]:checked')?.value ?? '';

        typeCards.forEach((card) => {
            card.classList.toggle('is-active', Boolean(type) && card.dataset.posOrderTypeCard === type);
        });

        if (type) {
            clearKasirOrderTypeError(root);
        }
    };

    const updateToolbar = (data) => {
        if (toolbarType) {
            if (data.order_type_label) {
                toolbarType.textContent = `${data.order_type_icon ?? ''} ${data.order_type_label}`.trim();
                toolbarType.classList.remove('hidden');
            } else {
                toolbarType.textContent = '';
                toolbarType.classList.add('hidden');
            }
        }

        if (toolbarCustomer) {
            if (data.customer_note) {
                toolbarCustomer.textContent = data.customer_note;
                toolbarCustomer.classList.remove('hidden');
            } else {
                toolbarCustomer.textContent = '';
                toolbarCustomer.classList.add('hidden');
            }
        }
    };

    const updateReceiptContext = (data) => {
        if (! receiptContext) {
            return;
        }

        receiptContext.innerHTML = '';

        if (data.order_type_label) {
            const typeBadge = document.createElement('span');
            typeBadge.className = 'pos-context-badge pos-context-badge-type';
            typeBadge.dataset.posReceiptType = '';
            typeBadge.textContent = `${data.order_type_icon ?? ''} ${data.order_type_label}`.trim();
            receiptContext.append(typeBadge);
        }

        if (data.customer_note) {
            const customerBadge = document.createElement('span');
            customerBadge.className = 'pos-context-badge pos-context-badge-customer';
            customerBadge.dataset.posReceiptCustomer = '';
            customerBadge.textContent = data.customer_note;
            receiptContext.append(customerBadge);
        }

        receiptContext.classList.toggle('hidden', receiptContext.children.length === 0);
    };

    const isOrderBarActive = () => {
        const active = document.activeElement;
        if (! active) {
            return false;
        }

        return bar.contains(active);
    };

    const saveOrderBar = async () => {
        if (saving) {
            return;
        }

        saving = true;
        setSaveStatus('saving', 'Menyimpan…');

        const formData = new FormData(bar);

        try {
            const response = await fetch(bar.action, {
                method: 'POST',
                body: formData,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const data = await response.json();

            if (! response.ok) {
                throw new Error(data.message || 'Gagal menyimpan.');
            }

            updateToolbar(data);
            updateReceiptContext(data);
            updateOrderSummary(data);
            clearKasirCustomerNameError(root);
            setSaveStatus('success', 'Tersimpan');
            window.setTimeout(() => {
                clearSaveStatus();
            }, 450);
        } catch (error) {
            setSaveStatus('error', error.message || 'Gagal menyimpan.');
            throw error;
        } finally {
            saving = false;
        }
    };

    root.__kasirFlushOrderBar = async () => {
        window.clearTimeout(saveTimer);

        while (saving) {
            await new Promise((resolve) => window.setTimeout(resolve, 40));
        }

        await saveOrderBar();
    };

    const queueSave = (delay = 0) => {
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(() => {
            void saveOrderBar().catch(() => {});
        }, delay);
    };

    orderBarToggle?.addEventListener('click', () => {
        setOrderBarExpanded(! bar.classList.contains('is-expanded'));
    });

    orderBarBackdrop?.addEventListener('click', () => {
        if (isOrderBarActive()) {
            return;
        }
        setOrderBarExpanded(false);
    });

    const productGrid = root.querySelector('.pos-product-grid');
    productGrid?.addEventListener('scroll', () => {
        if (isOrderBarActive()) {
            return;
        }
        collapseOrderBarOnMobile();
    }, { passive: true });

    const menuPanel = root.querySelector('[data-kasir-panel="menu"]');
    menuPanel?.addEventListener('click', (event) => {
        if (isOrderBarActive()) {
            return;
        }
        if (event.target.closest('[data-kasir-product], [data-kasir-category], [data-kasir-search]')) {
            collapseOrderBarOnMobile();
        }
    });

    typeRadios.forEach((radio) => {
        radio.addEventListener('change', () => {
            if (! radio.checked) {
                return;
            }

            syncTypeCards();
            queueSave(0);
            // Jangan auto-collapse — user biasanya lanjut isi nama.
        });
    });

    customerInput?.addEventListener('input', () => {
        if ((customerInput.value || '').trim()) {
            clearKasirCustomerNameError(root);
        }
        queueSave(700);
    });
    customerInput?.addEventListener('focus', () => {
        setOrderBarExpanded(true);
    });
    customerInput?.addEventListener('blur', () => {
        // Simpan tanpa menutup panel; tutup hanya lewat toggle / backdrop.
        queueSave(0);
    });

    syncTypeCards();
    setOrderBarExpanded(false);
}

function updateOrderTotalsDisplay(root, data) {
    root.querySelectorAll('[data-pos-order-totals]').forEach((block) => {
        if (data.subtotal_label) {
            block.querySelector('[data-pos-subtotal-label]')?.replaceChildren(document.createTextNode(data.subtotal_label));
            block.dataset.posSubtotal = String(data.subtotal ?? '');
        }

        const discountRow = block.querySelector('[data-pos-discount-row]');
        if (discountRow) {
            const hasDiscount = Number(data.discount_amount || 0) > 0;
            discountRow.classList.toggle('hidden', ! hasDiscount);

            if (hasDiscount && data.discount_label) {
                discountRow.querySelector('[data-pos-discount-label]')?.replaceChildren(document.createTextNode(data.discount_label));
            }
        }

        block.querySelectorAll('[data-pos-order-total]').forEach((el) => {
            if (data.total_label) {
                el.textContent = data.total_label;
            }

            if (data.total !== undefined) {
                el.dataset.posOrderTotal = String(data.total);
            }
        });
    });

    root.querySelectorAll('[data-kasir-pay-modal-total]').forEach((el) => {
        if (data.total_label) {
            el.textContent = data.total_label;
        }

        if (data.total !== undefined) {
            el.dataset.posOrderTotal = String(data.total);
        }
    });

    root.querySelectorAll('[data-pos-pay-submit-total]').forEach((el) => {
        if (data.total_label) {
            el.textContent = data.total_label;
        }
    });

    // Segarkan QRIS dinamis jika panel QRIS sedang terbuka / metode QRIS terpilih.
    const openPayForm = root.querySelector('[data-pos-pay-form-modal]');
    const qrisSelected = openPayForm?.querySelector('[data-pos-payment-method][value="qris"]:checked');
    if (qrisSelected && data.total !== undefined) {
        const panel = openPayForm.querySelector('[data-pos-qris-panel]');
        const url = panel?.getAttribute('data-qris-refresh-url');
        if (url) {
            fetch(`${url}?amount=${encodeURIComponent(data.total)}`, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((res) => (res.ok ? res.json() : null))
                .then((json) => {
                    const payload = json?.data || json;
                    if (! payload || ! panel) {
                        return;
                    }
                    const img = panel.querySelector('[data-qris-image]');
                    const amountLabel = panel.querySelector('[data-qris-amount-label]');
                    const hint = panel.querySelector('[data-qris-hint]');
                    const src = payload.enabled
                        ? (payload.qr_data_uri || payload.fallback_image_url)
                        : payload.fallback_image_url;
                    if (img && src) {
                        img.src = src;
                    }
                    if (amountLabel && payload.amount_label) {
                        amountLabel.textContent = payload.amount_label;
                    }
                    if (hint) {
                        hint.textContent = payload.enabled
                            ? 'Scan QRIS — nominal sudah terisi otomatis.'
                            : 'Scan QRIS lalu masukkan nominal manual (belum ada payload dinamis).';
                    }
                })
                .catch(() => {});
        }
    }

    const payDiscount = root.querySelector('[data-kasir-pay-modal-discount]');
    const payDiscountAmount = root.querySelector('[data-kasir-pay-modal-discount-amount]');
    if (payDiscount) {
        const hasDiscount = Number(data.discount_amount || 0) > 0;
        payDiscount.classList.toggle('hidden', ! hasDiscount);
        if (hasDiscount && data.discount_label && payDiscountAmount) {
            // discount_label is like "- Rp 9.300" — show amount part without leading dash for modal meta
            payDiscountAmount.textContent = String(data.discount_label).replace(/^\-\s*/, '');
        }
    }

    root.querySelector('[data-kasir-pay-button-total]')?.replaceChildren(
        document.createTextNode(data.total_label || ''),
    );

    const discountPanel = root.querySelector('[data-pos-discount-panel]');
    const summaryEl = discountPanel?.querySelector('[data-pos-discount-summary]');
    if (discountPanel && summaryEl) {
        const hasDiscount = Number(data.discount_amount || 0) > 0;
        discountPanel.dataset.hasDiscount = hasDiscount ? '1' : '0';
        summaryEl.textContent = hasDiscount && data.discount_label
            ? data.discount_label
            : 'Tambah diskon';
    }
}

function initPosDiscount(root) {
    const panel = root.querySelector('[data-pos-discount-panel]');
    const form = panel?.querySelector('[data-pos-discount-form]');

    if (! panel || ! form) {
        return;
    }

    const toggleBtn = panel.querySelector('[data-pos-discount-toggle]');
    const typeSelect = form.querySelector('[data-pos-discount-type]');
    const valueInput = form.querySelector('[data-pos-discount-value]');
    const controlsEl = form.querySelector('[data-pos-discount-controls]');
    const statusEl = form.querySelector('[data-pos-discount-status]') || panel.querySelector('[data-pos-discount-status]');
    const hintEl = form.querySelector('[data-pos-discount-hint]');
    const csrf = form.querySelector('input[name="_token"]')?.value;
    let saveTimer = null;
    let saving = false;

    const setExpanded = (expanded) => {
        panel.classList.toggle('is-expanded', expanded);
        toggleBtn?.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };

    const setStatus = (state, message) => {
        if (! statusEl) {
            return;
        }

        statusEl.classList.remove('hidden', 'is-saving', 'is-success', 'is-error');
        statusEl.classList.add(state === 'saving' ? 'is-saving' : state === 'error' ? 'is-error' : 'is-success');
        statusEl.textContent = message;
    };

    const clearStatus = () => {
        statusEl?.classList.add('hidden');
    };

    const syncDiscountControls = () => {
        const enabled = Boolean(typeSelect?.value);

        if (valueInput) {
            valueInput.disabled = ! enabled;

            if (! enabled) {
                valueInput.value = '';
            }

            valueInput.placeholder = typeSelect?.value === 'percent' ? 'cth. 10' : 'cth. 5000';
        }

        controlsEl?.classList.toggle('is-no-discount', ! enabled);

        if (! hintEl || ! typeSelect) {
            return;
        }

        hintEl.textContent = typeSelect.value === 'percent'
            ? 'Contoh: isi 10 untuk diskon 10% dari subtotal.'
            : typeSelect.value === 'amount'
                ? 'Contoh: isi 5000 untuk potong Rp 5.000.'
                : 'Pilih jenis diskon, lalu isi nilainya.';
    };

    const saveDiscount = async () => {
        if (saving || ! csrf) {
            return;
        }

        saving = true;
        setStatus('saving', 'Menyimpan...');

        const body = new FormData();
        body.append('_token', csrf);
        body.append('_method', 'PATCH');
        body.append('discount_type', typeSelect?.value || '');
        body.append('discount_value', valueInput?.value || '0');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body,
            });

            const data = await response.json();

            if (! response.ok) {
                throw new Error(data.message || 'Gagal menyimpan diskon.');
            }

            updateOrderTotalsDisplay(root, data);
            setStatus('success', 'Tersimpan');
            window.setTimeout(clearStatus, 1200);
        } catch (error) {
            setStatus('error', error.message || 'Gagal menyimpan diskon.');
        } finally {
            saving = false;
        }
    };

    const queueSave = () => {
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(saveDiscount, 450);
    };

    toggleBtn?.addEventListener('click', () => {
        setExpanded(! panel.classList.contains('is-expanded'));
    });

    typeSelect?.addEventListener('change', () => {
        syncDiscountControls();
        queueSave();
    });

    valueInput?.addEventListener('input', queueSave);
    valueInput?.addEventListener('blur', saveDiscount);

    syncDiscountControls();
    setExpanded(panel.classList.contains('is-expanded'));
}

function initPosPendingPanel(root) {
    if (root.dataset.boundPendingToggle === '1') {
        return;
    }
    root.dataset.boundPendingToggle = '1';

    root.addEventListener('click', (event) => {
        const currentOrder = event.target.closest('[data-open-current-order]');
        if (currentOrder) {
            event.preventDefault();

            if (window.innerWidth < POS_DESKTOP_BP && typeof kasirSetPanel === 'function') {
                kasirSetPanel('cart');
                return;
            }

            root.querySelector('[data-kasir-panel="cart"]')?.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
            });
            return;
        }

        const toggle = event.target.closest('[data-pos-pending-toggle]');
        if (! toggle) {
            return;
        }

        const panel = toggle.closest('[data-pos-pending]');
        if (! panel) {
            return;
        }

        const expanded = ! panel.classList.contains('is-expanded');
        panel.classList.toggle('is-expanded', expanded);
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        try {
            sessionStorage.setItem('kasir-pending-expanded', expanded ? '1' : '0');
        } catch (e) {
            // ignore
        }
    });
}

function initPosFlash(root) {
    const flashes = document.querySelectorAll('[data-pos-flash]');

    flashes.forEach((flash) => {
        window.setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transition = 'opacity 0.3s ease';
            window.setTimeout(() => flash.remove(), 320);
        }, 3200);
    });
}

function openKasirOverlay(modal) {
    if (! modal) {
        return;
    }

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('pos-modal-open');
}

function closeKasirOverlay(modal) {
    if (! modal) {
        return;
    }

    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');

    const anyOpen = document.querySelector('[data-kasir-modal]:not(.hidden), [data-kasir-detail-modal]:not(.hidden), [data-kasir-pay-modal]:not(.hidden), [data-kasir-confirm-modal]:not(.hidden), [data-kasir-deliver-modal]:not(.hidden)');
    if (! anyOpen) {
        document.body.classList.remove('pos-modal-open');
        document.body.classList.remove('overflow-hidden');
    }
}

function initPosPayModal(root) {
    const payModal = root.querySelector('[data-kasir-pay-modal]');
    const confirmModal = root.querySelector('[data-kasir-confirm-modal]');

    payModal?.querySelectorAll('[data-kasir-close-pay]').forEach((el) => {
        el.addEventListener('click', () => closeKasirOverlay(payModal));
    });

    confirmModal?.querySelectorAll('[data-kasir-close-confirm-modal]').forEach((el) => {
        el.addEventListener('click', () => closeKasirOverlay(confirmModal));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        if (payModal && ! payModal.classList.contains('hidden')) {
            closeKasirOverlay(payModal);
        }

        if (confirmModal && ! confirmModal.classList.contains('hidden')) {
            closeKasirOverlay(confirmModal);
        }
    });
}

function initPosCashPayment(root) {
    const forms = root.querySelectorAll('[data-pos-pay-form]');
    if (forms.length === 0) {
        return;
    }

    forms.forEach((form) => {
        const cashPanel = form.querySelector('[data-pos-cash-panel]');
        const receivedInput = form.querySelector('[data-pos-amount-received]');
        const receivedValue = form.querySelector('[data-pos-amount-received-value]');
        const changeAmount = form.querySelector('[data-pos-change-amount]');

        const formatRupiah = (value) => `Rp ${Math.round(value).toLocaleString('id-ID')}`;

        const readReceivedAmount = () => parseRupiahInput(receivedInput?.value || '0');

        const readTotal = () => parseFloat(
            form.querySelector('[data-pos-order-total]')?.dataset.posOrderTotal
            || root.querySelector('[data-pos-order-total]')?.dataset.posOrderTotal
            || root.dataset.posTotal
            || '0',
        );

        const syncChange = () => {
            const received = readReceivedAmount();
            const total = readTotal();
            const change = Math.max(0, received - total);

            if (changeAmount) {
                changeAmount.textContent = formatRupiah(change);
            }
        };

        const syncReceivedAmount = () => {
            const numeric = readReceivedAmount();

            if (receivedValue) {
                receivedValue.value = receivedInput?.value === '' ? '' : numeric;
            }

            if (receivedInput) {
                receivedInput.value = formatRupiahInput(receivedInput.value);
            }
        };

        const proofPanel = form.querySelector('[data-pos-proof-panel]');
        const qrisPanel = form.querySelector('[data-pos-qris-panel]');
        const proofInput = form.querySelector('[data-pos-payment-proof]');
        const proofPreview = form.querySelector('[data-pos-proof-preview]');
        const proofPreviewImage = form.querySelector('[data-pos-proof-preview-image]');
        const proofTitle = form.querySelector('[data-pos-proof-title]');
        const proofError = form.querySelector('[data-pos-proof-error]');
        const proofClear = form.querySelector('[data-pos-proof-clear]');
        const proofPickRow = form.querySelector('[data-pos-proof-pick-row]');
        const proofPickers = form.querySelectorAll('[data-pos-payment-proof-pick]');
        let proofObjectUrl = null;

        const looksLikeImageFile = (file) => {
            const type = (file?.type || '').toLowerCase();
            if (type.startsWith('image/')) {
                return true;
            }

            return /\.(jpe?g|png|webp|heic|heif|gif)$/i.test(file?.name || '');
        };

        const assignProofToInput = (file) => {
            if (! (proofInput instanceof HTMLInputElement) || ! file) {
                return false;
            }

            try {
                const dt = new DataTransfer();
                dt.items.add(file);
                proofInput.files = dt.files;
                return proofInput.files.length > 0;
            } catch {
                return false;
            }
        };

        const clearProofPreview = () => {
            if (proofObjectUrl) {
                URL.revokeObjectURL(proofObjectUrl);
                proofObjectUrl = null;
            }

            if (proofInput) {
                proofInput.value = '';
            }

            proofPreview?.classList.add('hidden');
            proofPickRow?.classList.remove('hidden');
            proofError?.classList.add('hidden');

            if (proofPreviewImage) {
                proofPreviewImage.removeAttribute('src');
            }

            if (proofTitle) {
                proofTitle.textContent = 'Ambil / unggah foto';
            }
        };

        const showProofPreview = (file) => {
            if (! file || ! proofPreviewImage) {
                return;
            }

            if (proofObjectUrl) {
                URL.revokeObjectURL(proofObjectUrl);
            }

            proofObjectUrl = URL.createObjectURL(file);
            proofPreviewImage.src = proofObjectUrl;
            proofPreview?.classList.remove('hidden');
            proofPickRow?.classList.add('hidden');
            proofError?.classList.add('hidden');

            if (proofTitle) {
                proofTitle.textContent = file.name || 'Bukti terpilih';
            }
        };

        const handlePickedProof = (file) => {
            if (! file) {
                return;
            }

            if (! looksLikeImageFile(file)) {
                clearProofPreview();
                proofError?.classList.remove('hidden');
                if (proofError) {
                    proofError.textContent = 'File harus berupa gambar.';
                }
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                clearProofPreview();
                proofError?.classList.remove('hidden');
                if (proofError) {
                    proofError.textContent = 'Ukuran maksimal 5 MB.';
                }
                return;
            }

            assignProofToInput(file);
            showProofPreview(file);
        };

        const applyQrisPayload = (payload) => {
            if (! payload || ! qrisPanel) {
                return;
            }

            const box = qrisPanel.querySelector('[data-qris-dynamic]');
            const img = qrisPanel.querySelector('[data-qris-image]');
            const amountLabel = qrisPanel.querySelector('[data-qris-amount-label]');
            const hint = qrisPanel.querySelector('[data-qris-hint]');
            const src = payload.enabled
                ? (payload.qr_data_uri || payload.fallback_image_url)
                : (payload.fallback_image_url || img?.getAttribute('src'));

            if (img && src) {
                img.src = src;
            }

            if (box) {
                box.dataset.qrisMode = payload.mode || (payload.enabled ? 'dynamic' : 'static');
                if (payload.amount !== undefined) {
                    box.dataset.qrisAmount = String(payload.amount);
                }
            }

            if (amountLabel && payload.amount_label) {
                amountLabel.textContent = payload.amount_label;
            }

            if (hint) {
                hint.textContent = payload.enabled
                    ? 'Scan QRIS — nominal sudah terisi otomatis.'
                    : 'Scan QRIS lalu masukkan nominal manual (belum ada payload dinamis).';
            }
        };

        let qrisRefreshToken = 0;
        const refreshQrisDynamic = async () => {
            if (! qrisPanel) {
                return;
            }

            const url = qrisPanel.getAttribute('data-qris-refresh-url');
            if (! url) {
                return;
            }

            const totalEl = form.querySelector('[data-pos-order-total]')
                || root.querySelector('[data-kasir-pay-modal-total]');
            const amount = Number(totalEl?.dataset?.posOrderTotal || 0);
            const token = ++qrisRefreshToken;

            try {
                const res = await fetch(`${url}?amount=${encodeURIComponent(amount)}`, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (! res.ok) {
                    return;
                }
                const json = await res.json();
                if (token !== qrisRefreshToken) {
                    return;
                }
                applyQrisPayload(json.data || json);
            } catch (e) {
                // biarkan QR lama
            }
        };

        const syncPaymentMethod = () => {
            const method = form.querySelector('[data-pos-payment-method]:checked')?.value;
            const isCash = method === 'cash';
            const isQris = method === 'qris';
            const showsProof = method === 'qris' || method === 'transfer';

            cashPanel?.classList.toggle('hidden', ! isCash);
            qrisPanel?.classList.toggle('hidden', ! isQris);
            proofPanel?.classList.toggle('hidden', ! showsProof);
            form.classList.toggle('is-cash-pay', isCash);
            form.classList.toggle('is-qris-pay', isQris);
            form.classList.toggle('is-noncash-pay', showsProof);

            if (proofInput) {
                proofInput.required = false;
            }

            if (! showsProof) {
                clearProofPreview();
            }

            if (isQris) {
                void refreshQrisDynamic();
            }
        };

        form.querySelectorAll('[data-pos-payment-method]').forEach((radio) => {
            radio.addEventListener('change', () => {
                form.querySelectorAll('.pos-pay-option').forEach((option) => {
                    const input = option.querySelector('input[type="radio"]');
                    option.classList.toggle('is-selected', Boolean(input?.checked));
                });
                syncPaymentMethod();
            });
        });

        proofInput?.addEventListener('change', () => {
            const file = proofInput.files?.[0];
            if (! file) {
                clearProofPreview();
                return;
            }

            handlePickedProof(file);
        });

        proofPickers.forEach((picker) => {
            picker.addEventListener('change', () => {
                const file = picker.files?.[0];
                handlePickedProof(file);
                picker.value = '';
            });
        });

        proofClear?.addEventListener('click', () => {
            clearProofPreview();
        });

        receivedInput?.addEventListener('input', () => {
            const numeric = receivedInput.value === ''
                ? 0
                : formatRupiahInputLive(receivedInput);

            if (receivedValue) {
                receivedValue.value = receivedInput.value === '' ? '' : numeric;
            }

            syncChange();
        });

        receivedInput?.addEventListener('blur', syncReceivedAmount);

        form.addEventListener('submit', (event) => {
            syncReceivedAmount();

            if (! window.confirm('Proses pembayaran? Biaya pokok akan tercatat otomatis.')) {
                event.preventDefault();
            }
        });

        const isMobilePay = () => window.innerWidth < 1024;

        const syncKeyboardInset = () => {
            if (! isMobilePay() || ! window.visualViewport) {
                document.documentElement.style.removeProperty('--keyboard-inset');

                return;
            }

            const inset = Math.max(
                0,
                window.innerHeight - window.visualViewport.height - window.visualViewport.offsetTop,
            );

            document.documentElement.style.setProperty('--keyboard-inset', `${inset}px`);
        };

        const scrollCashInputIntoView = () => {
            if (! isMobilePay() || ! receivedInput) {
                return;
            }

            window.requestAnimationFrame(() => {
                receivedInput.scrollIntoView({ block: 'center', behavior: 'smooth' });
            });
        };

        receivedInput?.addEventListener('focus', () => {
            cashPanel?.classList.add('is-input-focused');
            syncKeyboardInset();
            scrollCashInputIntoView();
            window.setTimeout(scrollCashInputIntoView, 300);
        });

        receivedInput?.addEventListener('blur', () => {
            cashPanel?.classList.remove('is-input-focused');

            window.setTimeout(() => {
                if (document.activeElement !== receivedInput) {
                    document.documentElement.style.removeProperty('--keyboard-inset');
                }
            }, 120);
        });

        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', () => {
                syncKeyboardInset();

                if (document.activeElement === receivedInput) {
                    scrollCashInputIntoView();
                }
            });
        }

        syncPaymentMethod();
        syncChange();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initKasirPos();
    initDeliverModal(document);
    initThermalPrintLinks();
});

const THERMAL_PAPER_KEY = 'pos-thermal-paper';

function resolveThermalPaperPreference() {
    try {
        const saved = localStorage.getItem(THERMAL_PAPER_KEY);
        if (saved === '58mm' || saved === '80mm') {
            return saved;
        }
    } catch {
        // ignore
    }

    return '58mm';
}

function withThermalPaper(url) {
    if (! url) {
        return url;
    }

    try {
        const parsed = new URL(url, window.location.origin);
        parsed.searchParams.set('paper', resolveThermalPaperPreference());
        return parsed.toString();
    } catch {
        const joiner = url.includes('?') ? '&' : '?';
        return `${url}${joiner}paper=${encodeURIComponent(resolveThermalPaperPreference())}`;
    }
}

/** Append ?paper=58mm|80mm ke link cetak dapur/bar agar cocok POS-58 & Rongta. */
function initThermalPrintLinks() {
    document.addEventListener('click', (event) => {
        const link = event.target.closest?.('[data-thermal-print-link]');
        if (! link || ! link.href) {
            return;
        }

        event.preventDefault();
        window.open(withThermalPaper(link.href), '_blank', 'noopener');
    });
}

let kasirSetPanel = null;

function bindOrderActionButtons(root) {
    if (! kasirSetPanel) {
        return;
    }

    root.querySelectorAll('[data-kasir-go-menu]').forEach((btn) => {
        if (btn.dataset.boundGoMenu === '1') {
            return;
        }
        btn.dataset.boundGoMenu = '1';
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            kasirSetPanel('menu');
        });
    });

    root.querySelectorAll('[data-kasir-go-cart]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const activeTab = root.querySelector('[data-kasir-tab].is-active')?.dataset.kasirTab;

            if (activeTab !== 'cart') {
                kasirSetPanel('cart');
                return;
            }

            const pay = root.querySelector('[data-pos-receipt-pay], [data-pos-receipt-confirm]');
            if (pay) {
                window.requestAnimationFrame(() => {
                    pay.scrollIntoView({ block: 'end', behavior: 'smooth', inline: 'nearest' });
                });
            }
        });
    });

    root.querySelectorAll('[data-kasir-open-pay]').forEach((btn) => {
        if (btn.dataset.boundOpenPay === '1') {
            return;
        }
        btn.dataset.boundOpenPay = '1';
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const payModal = root.querySelector('[data-kasir-pay-modal]');
            if (payModal) {
                preparePosPayModal(payModal, root);
                openKasirOverlay(payModal);
            }
        });
    });

    root.querySelectorAll('[data-kasir-open-confirm]').forEach((btn) => {
        if (btn.dataset.boundOpenConfirm === '1') {
            return;
        }
        btn.dataset.boundOpenConfirm = '1';
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const confirmModal = root.querySelector('[data-kasir-confirm-modal]');
            if (confirmModal) {
                openKasirOverlay(confirmModal);
            }
        });
    });
}

function preparePosPayModal(payModal, root) {
    const form = payModal.querySelector('[data-pos-pay-form]');
    if (! form) {
        return;
    }

    const total = parseFloat(
        form.querySelector('[data-pos-order-total]')?.dataset.posOrderTotal
        || payModal.querySelector('[data-pos-order-total]')?.dataset.posOrderTotal
        || root?.dataset?.posTotal
        || '0',
    );

    const method = form.querySelector('[data-pos-payment-method]:checked')?.value;
    const receivedInput = form.querySelector('[data-pos-amount-received]');
    const receivedValue = form.querySelector('[data-pos-amount-received-value]');
    const changeAmount = form.querySelector('[data-pos-change-amount]');

    if (method === 'cash' && receivedInput && (! receivedInput.value || receivedInput.value === '0')) {
        const rounded = Math.round(total);
        receivedInput.value = formatRupiahInput(String(rounded));
        if (receivedValue) {
            receivedValue.value = String(rounded);
        }
        if (changeAmount) {
            changeAmount.textContent = `Rp ${Math.max(0, rounded - Math.round(total)).toLocaleString('id-ID')}`;
        }
    }
}

function reinitOrderDependentUi(root) {
    initPosDiscount(root);
    initPosPayModal(root);
    initPosCashPayment(root);
    bindOrderActionButtons(root);
    initPosPendingPanel(root);
    initItemDeliverToggle(root);
}

export function refreshKasirOrderUi(payload) {
    const root = document.getElementById('kasir-pos');
    if (! root || ! payload?.fragments) {
        return false;
    }

    if (payload.require_customer !== undefined) {
        root.dataset.kasirRequireCustomer = payload.require_customer ? '1' : '0';
    }

    const cartPanel = root.querySelector('[data-kasir-panel="cart"]');
    if (cartPanel && payload.fragments.cart) {
        cartPanel.innerHTML = payload.fragments.cart;
    }

    const paySlot = root.querySelector('[data-kasir-order-pay-slot]');
    if (paySlot && payload.fragments.pay_modal !== undefined) {
        paySlot.innerHTML = payload.fragments.pay_modal;
    }

    root.querySelector('[data-pos-mobile-checkout]')?.remove();
    if (payload.fragments.mobile_checkout) {
        const anchor = root.querySelector('[data-kasir-order-pay-slot]');
        anchor?.insertAdjacentHTML('beforebegin', payload.fragments.mobile_checkout);
    }

    if (payload.toolbar) {
        const chip = root.querySelector('.pos-order-chip-value');
        if (chip && payload.toolbar.order_number) {
            chip.textContent = payload.toolbar.order_number;
        }

        const typeChip = root.querySelector('[data-pos-toolbar-type]');
        if (typeChip) {
            if (payload.toolbar.order_type) {
                typeChip.textContent = payload.toolbar.order_type;
                typeChip.classList.remove('hidden');
            } else {
                typeChip.classList.add('hidden');
            }
        }

        const customerChip = root.querySelector('[data-pos-toolbar-customer]');
        if (customerChip) {
            if (payload.toolbar.customer_note) {
                customerChip.textContent = payload.toolbar.customer_note;
                customerChip.classList.remove('hidden');
            } else {
                customerChip.classList.add('hidden');
            }
        }

        const statusBadge = root.querySelector('.pos-toolbar-left .badge');
        if (statusBadge && payload.toolbar.status_label) {
            statusBadge.textContent = payload.toolbar.status_label;
            statusBadge.className = `badge max-lg:hidden ${payload.toolbar.status_badge}`;
        }
    }

    if (typeof payload.total === 'number') {
        root.dataset.posTotal = String(payload.total);
    }

    const itemCount = payload.item_count ?? 0;
    const cartCount = root.querySelector('[data-kasir-cart-count]');
    if (cartCount) {
        cartCount.textContent = String(itemCount);
        cartCount.classList.toggle('hidden', itemCount === 0);
    }

    const tabTotal = root.querySelector('.pos-view-tab-total');
    if (tabTotal) {
        if (itemCount > 0 && payload.toolbar?.formatted_total) {
            tabTotal.textContent = payload.toolbar.formatted_total;
            tabTotal.classList.remove('hidden');
        } else {
            tabTotal.classList.add('hidden');
        }
    }

    reinitOrderDependentUi(root);

    if (kasirSetPanel) {
        kasirSetPanel('cart');
    }

    return true;
}
