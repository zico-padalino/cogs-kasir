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

    const dineInFields = bar.querySelector('[data-pos-dine-in-fields]');
    const tableSelect = bar.querySelector('[data-pos-table-select]');
    const tableError = bar.querySelector('[data-pos-table-error]');
    const customerInput = bar.querySelector('[data-pos-customer-note]');
    const customerLabel = bar.querySelector('[data-pos-customer-label]');
    const customerHint = bar.querySelector('[data-pos-customer-hint]');
    const saveStatus = bar.querySelector('[data-pos-save-status]');
    const tablePills = bar.querySelectorAll('[data-pos-table-pill]');
    const typeCards = bar.querySelectorAll('[data-pos-order-type-card]');
    const typeRadios = bar.querySelectorAll('[data-pos-order-type]');
    const orderSummary = bar.querySelector('[data-pos-order-summary]');
    const orderBarToggle = bar.querySelector('[data-pos-order-bar-toggle]');

    const toolbarType = root.querySelector('[data-pos-toolbar-type]');
    const toolbarTable = root.querySelector('[data-pos-toolbar-table]');
    const toolbarCustomer = root.querySelector('[data-pos-toolbar-customer]');
    const receiptContext = root.querySelector('[data-pos-receipt-context]');

    let saveTimer = null;
    let saving = false;

    const activeType = () => bar.querySelector('[data-pos-order-type]:checked')?.value ?? 'takeaway';

    const setOrderBarExpanded = (expanded) => {
        bar.classList.toggle('is-expanded', expanded);

        if (orderBarToggle) {
            orderBarToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        }
    };

    const buildOrderSummary = (data) => {
        const parts = [];

        if (data.order_type_label) {
            parts.push(`${data.order_type_icon ?? ''} ${data.order_type_label}`.trim());
        }

        if (data.table_label) {
            parts.push(data.table_label);
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
        const type = activeType();

        typeCards.forEach((card) => {
            card.classList.toggle('is-active', card.dataset.posOrderTypeCard === type);
        });
    };

    const syncDineInPanel = () => {
        const isDineIn = activeType() === 'dine_in';

        dineInFields?.classList.toggle('hidden', ! isDineIn);

        if (customerLabel) {
            customerLabel.textContent = isDineIn ? 'Nama pelanggan' : 'Nama / nomor antrian';
        }

        if (customerInput) {
            customerInput.placeholder = isDineIn
                ? 'Opsional — untuk panggilan'
                : 'Contoh: Budi / A-12';
        }

        if (customerHint) {
            customerHint.textContent = isDineIn
                ? 'Opsional jika sudah ada meja.'
                : 'Memudahkan kasir memanggil saat pesanan siap.';
        }

        if (! isDineIn) {
            tableError?.classList.add('hidden');
            tablePills.forEach((pill) => pill.classList.remove('is-active'));
            if (tableSelect) {
                tableSelect.value = '';
            }
        }
    };

    const syncTablePills = () => {
        const value = tableSelect?.value ?? '';

        tablePills.forEach((pill) => {
            pill.classList.toggle('is-active', pill.dataset.tableId === value);
        });
    };

    const updateToolbar = (data) => {
        if (toolbarType && data.order_type_label) {
            toolbarType.textContent = `${data.order_type_icon ?? ''} ${data.order_type_label}`.trim();
            toolbarType.classList.remove('hidden');
        }

        if (toolbarTable) {
            if (data.table_label) {
                toolbarTable.textContent = data.table_label;
                toolbarTable.classList.remove('hidden');
            } else {
                toolbarTable.textContent = '';
                toolbarTable.classList.add('hidden');
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

        if (data.table_label) {
            const tableBadge = document.createElement('span');
            tableBadge.className = 'pos-context-badge pos-context-badge-table';
            tableBadge.dataset.posReceiptTable = '';
            tableBadge.textContent = `Meja ${data.table_label}`;
            receiptContext.append(tableBadge);
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

    const canAutoSave = () => {
        if (activeType() === 'dine_in') {
            return Boolean(tableSelect?.value);
        }

        return true;
    };

    const saveOrderBar = async () => {
        if (saving || ! canAutoSave()) {
            if (activeType() === 'dine_in' && ! tableSelect?.value) {
                tableError?.classList.remove('hidden');
            }

            return;
        }

        tableError?.classList.add('hidden');
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
            window.setTimeout(() => {
                clearSaveStatus();
                setOrderBarExpanded(false);
            }, 1200);
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

    typeRadios.forEach((radio) => {
        radio.addEventListener('change', () => {
            if (! radio.checked) {
                return;
            }

            syncTypeCards();
            syncDineInPanel();

            if (radio.value === 'dine_in') {
                setOrderBarExpanded(true);
            }

            if (radio.value === 'takeaway') {
                queueSave(0);
                return;
            }

            if (tableSelect?.value) {
                queueSave(0);
            } else {
                tableError?.classList.remove('hidden');
            }
        });
    });

    tablePills.forEach((pill) => {
        pill.addEventListener('click', () => {
            if (! tableSelect) {
                return;
            }

            tableSelect.value = pill.dataset.tableId ?? '';
            syncTablePills();
            tableError?.classList.add('hidden');
            queueSave(0);
        });
    });

    customerInput?.addEventListener('input', () => queueSave(700));
    customerInput?.addEventListener('blur', () => queueSave(0));

    syncTypeCards();
    syncDineInPanel();
    syncTablePills();
    setOrderBarExpanded(false);
}

function initPosPendingPanel(root) {
    const panel = root.querySelector('[data-pos-pending]');
    const toggle = panel?.querySelector('[data-pos-pending-toggle]');

    if (! panel || ! toggle) {
        return;
    }

    const setExpanded = (expanded) => {
        panel.classList.toggle('is-expanded', expanded);
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };

    toggle.addEventListener('click', () => {
        setExpanded(! panel.classList.contains('is-expanded'));
    });

    setExpanded(false);
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
    const changeAmount = form.querySelector('[data-pos-change-amount]');

    const formatRupiah = (value) => `Rp ${Math.round(value).toLocaleString('id-ID')}`;

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

    const syncChange = () => {
        const received = parseFloat(receivedInput?.value || '0');
        const change = Math.max(0, received - total);

        if (changeAmount) {
            changeAmount.textContent = formatRupiah(change);
        }
    };

    form.querySelectorAll('[data-pos-payment-method]').forEach((radio) => {
        radio.addEventListener('change', syncPaymentMethod);
    });

    receivedInput?.addEventListener('input', syncChange);

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
