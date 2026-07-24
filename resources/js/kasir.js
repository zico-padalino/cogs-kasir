/**
 * Kasir POS — tab Menu/Pesanan, pencarian, modal tambah item, detail produk.
 */
import { formatRupiahInput, formatRupiahInputLive, parseRupiahInput } from './rupiah';

const POS_DESKTOP_BP = 1024;

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
                openAddModal(readProductCard(card));
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

    initBrowserViewportChrome();
    initKasirModals(root);
    initPosCategoryTabs(root);
    initPosOrderBar(root);
    initPosDiscount(root);
    initPosCashPayment(root);
    initPosPayModal(root);
    initPosPendingPanel(root);
    initPosFlash(root);

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

            const payModal = root.querySelector('[data-kasir-pay-modal]');
            const confirmModal = root.querySelector('[data-kasir-confirm-modal]');

            // Order kasir siap bayar → buka modal pembayaran
            if (payModal) {
                openKasirOverlay(payModal);
                return;
            }

            // Online masih menunggu → buka modal konfirmasi
            if (confirmModal) {
                openKasirOverlay(confirmModal);
                return;
            }

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
        goCartLabel.textContent = 'Bayar';
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
        const type = bar.querySelector('[data-pos-order-type]:checked')?.value ?? 'takeaway';

        typeCards.forEach((card) => {
            card.classList.toggle('is-active', card.dataset.posOrderTypeCard === type);
        });
    };

    const updateToolbar = (data) => {
        if (toolbarType && data.order_type_label) {
            toolbarType.textContent = `${data.order_type_icon ?? ''} ${data.order_type_label}`.trim();
            toolbarType.classList.remove('hidden');
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
            setSaveStatus('success', 'Tersimpan');
            const collapseDelay = window.innerWidth < POS_DESKTOP_BP ? 350 : 450;
            window.setTimeout(() => {
                clearSaveStatus();
                setOrderBarExpanded(false);
            }, collapseDelay);
        } catch (error) {
            setSaveStatus('error', error.message || 'Gagal menyimpan.');
        } finally {
            saving = false;
        }
    };

    const queueSave = (delay = 0) => {
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(saveOrderBar, delay);
    };

    orderBarToggle?.addEventListener('click', () => {
        setOrderBarExpanded(! bar.classList.contains('is-expanded'));
    });

    orderBarBackdrop?.addEventListener('click', () => {
        setOrderBarExpanded(false);
    });

    const productGrid = root.querySelector('.pos-product-grid');
    productGrid?.addEventListener('scroll', collapseOrderBarOnMobile, { passive: true });

    const menuPanel = root.querySelector('[data-kasir-panel="menu"]');
    menuPanel?.addEventListener('click', (event) => {
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

            if (window.innerWidth < POS_DESKTOP_BP) {
                setOrderBarExpanded(false);
            }
        });
    });

    customerInput?.addEventListener('input', () => queueSave(700));
    customerInput?.addEventListener('blur', () => queueSave(0));

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
    root.addEventListener('click', (event) => {
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

    const anyOpen = document.querySelector('[data-kasir-modal]:not(.hidden), [data-kasir-detail-modal]:not(.hidden), [data-kasir-pay-modal]:not(.hidden), [data-kasir-confirm-modal]:not(.hidden)');
    if (! anyOpen) {
        document.body.classList.remove('pos-modal-open');
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
        const proofDrop = form.querySelector('.pos-proof-drop');
        let proofObjectUrl = null;

        const clearProofPreview = () => {
            if (proofObjectUrl) {
                URL.revokeObjectURL(proofObjectUrl);
                proofObjectUrl = null;
            }

            if (proofInput) {
                proofInput.value = '';
            }

            proofPreview?.classList.add('hidden');
            proofDrop?.classList.remove('hidden');
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
            proofDrop?.classList.add('hidden');
            proofError?.classList.add('hidden');

            if (proofTitle) {
                proofTitle.textContent = file.name || 'Bukti terpilih';
            }
        };

        const syncPaymentMethod = () => {
            const method = form.querySelector('[data-pos-payment-method]:checked')?.value;
            const isCash = method === 'cash';
            const isQris = method === 'qris';
            const needsProof = method === 'qris' || method === 'transfer';

            cashPanel?.classList.toggle('hidden', ! isCash);
            qrisPanel?.classList.toggle('hidden', ! isQris);
            proofPanel?.classList.toggle('hidden', ! needsProof);
            form.classList.toggle('is-cash-pay', isCash);
            form.classList.toggle('is-qris-pay', isQris);
            form.classList.toggle('is-noncash-pay', needsProof);

            if (proofInput) {
                proofInput.required = needsProof;
            }

            if (! needsProof) {
                clearProofPreview();
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

            if (! file.type.startsWith('image/')) {
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

            showProofPreview(file);
        });

        proofClear?.addEventListener('click', () => {
            clearProofPreview();
            proofInput?.click();
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

            const method = form.querySelector('[data-pos-payment-method]:checked')?.value;
            const needsProof = method === 'qris' || method === 'transfer';

            if (needsProof && ! proofInput?.files?.length) {
                event.preventDefault();
                proofError?.classList.remove('hidden');
                if (proofError) {
                    proofError.textContent = 'Bukti pembayaran wajib untuk QRIS / Transfer.';
                }
                proofPanel?.scrollIntoView({ block: 'center', behavior: 'smooth' });
                return;
            }

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

document.addEventListener('DOMContentLoaded', initKasirPos);

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

            const payModal = root.querySelector('[data-kasir-pay-modal]');
            const confirmModal = root.querySelector('[data-kasir-confirm-modal]');

            if (payModal) {
                openKasirOverlay(payModal);
                return;
            }

            if (confirmModal) {
                openKasirOverlay(confirmModal);
                return;
            }

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
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const payModal = root.querySelector('[data-kasir-pay-modal]');
            if (payModal) {
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
}

function reinitOrderDependentUi(root) {
    initPosDiscount(root);
    initPosPayModal(root);
    initPosCashPayment(root);
    bindOrderActionButtons(root);
    initPosPendingPanel(root);
}

export function refreshKasirOrderUi(payload) {
    const root = document.getElementById('kasir-pos');
    if (! root || ! payload?.fragments) {
        return false;
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
