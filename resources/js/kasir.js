/**
 * Kasir POS — tab Menu/Pesanan, pencarian, modal tambah item, detail produk.
 */
import { formatRupiahInput, formatRupiahInputLive, parseRupiahInput } from './rupiah';

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
        if (! product || ! product.id || product.priceValue <= 0) {
            return;
        }

        activeProduct = product;
        maxQty = Number.isFinite(product.max) && product.max > 0 ? product.max : 99;

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

    initKasirModals(root);
    initPosCategoryTabs(root);
    initPosOrderBar(root);
    initPosCashPayment(root);
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
            window.setTimeout(() => scrollToPayDock(root), 120);
        }
    };

    const scrollToPayDock = (scope) => {
        const pay = scope.querySelector('[data-pos-receipt-pay], [data-pos-receipt-confirm]');
        if (! pay) {
            return;
        }

        const panel = scope.querySelector('[data-kasir-panel="cart"] .pos-receipt-body');
        if (panel && panel.contains(pay) === false) {
            pay.scrollIntoView({ block: 'nearest', behavior: 'smooth', inline: 'nearest' });
            return;
        }

        // Jangan scroll viewport/toolbar — cukup geser area item jika perlu
        const payTop = pay.getBoundingClientRect().top;
        const toolbar = scope.querySelector('.pos-toolbar');
        const toolbarBottom = toolbar?.getBoundingClientRect().bottom ?? 0;

        if (payTop < toolbarBottom + 8) {
            panel?.scrollBy({ top: payTop - toolbarBottom - 8, behavior: 'smooth' });
        }
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => setPanel(tab.dataset.kasirTab));
    });

    root.querySelectorAll('[data-kasir-go-cart]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const activeTab = root.querySelector('[data-kasir-tab].is-active')?.dataset.kasirTab;

            if (activeTab !== 'cart') {
                setPanel('cart');
                return;
            }

            scrollToPayDock(root);

            const submitBtn = root.querySelector('[data-pos-pay-submit]');
            if (submitBtn) {
                window.setTimeout(() => submitBtn.focus({ preventScroll: true }), 180);
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
            root.classList.remove('is-mobile-cart-tab', 'is-mobile-menu-tab');
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

    root.classList.toggle('is-mobile-cart-tab', isMobile && activeTab === 'cart');
    root.classList.toggle('is-mobile-menu-tab', isMobile && activeTab === 'menu');

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
        if (window.innerWidth < POS_DESKTOP_BP && bar.classList.contains('is-expanded')) {
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
            const collapseDelay = window.innerWidth < POS_DESKTOP_BP ? 350 : 1200;
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

function initPosCashPayment(root) {
    const form = root.querySelector('[data-pos-pay-form]');
    if (! form) {
        return;
    }

    const cashPanel = form.querySelector('[data-pos-cash-panel]');
    const totalEl = form.querySelector('[data-pos-order-total]');
    const total = parseFloat(totalEl?.dataset.posOrderTotal || root.dataset.posTotal || '0');
    const receivedInput = form.querySelector('[data-pos-amount-received]');
    const receivedValue = form.querySelector('[data-pos-amount-received-value]');
    const changeAmount = form.querySelector('[data-pos-change-amount]');

    const formatRupiah = (value) => `Rp ${Math.round(value).toLocaleString('id-ID')}`;

    const readReceivedAmount = () => parseRupiahInput(receivedInput?.value || '0');

    const syncReceivedAmount = () => {
        const numeric = readReceivedAmount();

        if (receivedValue) {
            receivedValue.value = receivedInput?.value === '' ? '' : numeric;
        }

        if (receivedInput) {
            receivedInput.value = formatRupiahInput(receivedInput.value);
        }
    };

    const syncChange = () => {
        const received = readReceivedAmount();
        const change = Math.max(0, received - total);

        if (changeAmount) {
            changeAmount.textContent = formatRupiah(change);
        }
    };

    const syncPaymentMethod = () => {
        const method = form.querySelector('[data-pos-payment-method]:checked')?.value;
        const isCash = method === 'cash';

        cashPanel?.classList.toggle('hidden', ! isCash);
        form.classList.toggle('is-cash-pay', isCash);

        if (isCash && window.innerWidth < 1024) {
            window.setTimeout(() => {
                cashPanel?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }, 80);
        }
    };

    form.querySelectorAll('[data-pos-payment-method]').forEach((radio) => {
        radio.addEventListener('change', syncPaymentMethod);
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

    form.addEventListener('submit', () => {
        syncReceivedAmount();
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
}

document.addEventListener('DOMContentLoaded', initKasirPos);
