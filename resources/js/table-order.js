function initOrderSubmit() {
    const button = document.querySelector('[data-order-submit]');
    const form = document.getElementById('order-submit-form');

    if (! (button instanceof HTMLElement) || ! (form instanceof HTMLFormElement)) {
        return;
    }

    button.addEventListener('click', () => {
        if (typeof form.reportValidity === 'function' && ! form.reportValidity()) {
            form.querySelector(':invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });

            return;
        }

        if (! window.confirm('Lanjut ke pembayaran? Setelah ini pilih QRIS atau tunai di kasir.')) {
            return;
        }

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });
}

function initOrderItemNotes() {
    const notes = document.querySelectorAll('.pos-order-item-note-details');
    if (! notes.length) {
        return;
    }

    notes.forEach((details) => {
        details.addEventListener('toggle', () => {
            if (! details.open) {
                return;
            }

            notes.forEach((other) => {
                if (other !== details) {
                    other.open = false;
                }
            });

            const input = details.querySelector('textarea');
            window.requestAnimationFrame(() => {
                input?.focus();
                input?.setSelectionRange(input.value.length, input.value.length);
            });
        });
    });
}

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

    const main = root.querySelector('.order-table-main');
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

            if (window.matchMedia('(max-width: 1023px)').matches) {
                main?.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    });
}

function initOrderSearch() {
    const input = document.querySelector('[data-order-search]');
    if (! input) {
        return;
    }

    const emptyState = document.querySelector('[data-order-search-empty]');

    input.addEventListener('input', () => {
        const query = input.value.trim().toLowerCase();
        let visibleCount = 0;

        document.querySelectorAll('[data-order-product]').forEach((card) => {
            const key = card.dataset.orderProduct || '';
            const isVisible = query === '' || key.includes(query);
            card.classList.toggle('hidden', ! isVisible);
            visibleCount += isVisible ? 1 : 0;
        });

        emptyState?.classList.toggle('hidden', query === '' || visibleCount > 0);
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
    initOrderItemNotes();
    initOrderSubmit();
    initOrderPayChoice();
    initOrderKasirConfirmation();
});

function initOrderPayChoice() {
    const root = document.querySelector('[data-order-pay-choice]');
    if (! root) {
        return;
    }

    const buttons = root.querySelectorAll('[data-order-pay-method]');
    const panels = root.querySelectorAll('[data-order-pay-panel]');
    const proofInput = root.querySelector('[data-order-payment-proof]');
    const preview = root.querySelector('[data-order-proof-preview]');
    const previewImg = root.querySelector('[data-order-proof-preview-img]');
    const form = root.querySelector('[data-order-qris-pay-form]');
    let objectUrl = null;

    const showMethod = (method) => {
        buttons.forEach((btn) => {
            btn.classList.toggle('is-active', btn.getAttribute('data-order-pay-method') === method);
        });
        panels.forEach((panel) => {
            panel.classList.toggle('hidden', panel.getAttribute('data-order-pay-panel') !== method);
        });
    };

    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            showMethod(btn.getAttribute('data-order-pay-method') || 'qris');
        });
    });

    proofInput?.addEventListener('change', () => {
        const file = proofInput.files?.[0];
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        if (! file || ! previewImg || ! preview) {
            preview?.classList.add('hidden');
            return;
        }
        objectUrl = URL.createObjectURL(file);
        previewImg.src = objectUrl;
        preview.classList.remove('hidden');
    });

    form?.addEventListener('submit', (event) => {
        if (! proofInput?.files?.length) {
            event.preventDefault();
            window.alert('Unggah bukti pembayaran dulu.');
            return;
        }
        if (! window.confirm('Kirim bukti pembayaran? Pesanan akan dicatat lunas dan masuk ke kasir.')) {
            event.preventDefault();
        }
    });

    const cashForm = root.querySelector('[data-order-cash-send-form]');
    cashForm?.addEventListener('submit', (event) => {
        if (! window.confirm('Kirim pesanan ke kasir untuk bayar tunai?')) {
            event.preventDefault();
        }
    });

    initOrderQrisAppLinks(root);
    showMethod('qris');
}

function initOrderQrisAppLinks(root) {
    const box = root.querySelector('[data-order-qris-apps]');
    if (! box) {
        return;
    }

    const payload = box.getAttribute('data-qris-payload') || '';
    const imagePath = box.getAttribute('data-qris-image') || '';
    const hint = box.querySelector('[data-qris-app-hint]');
    const imageUrl = imagePath ? `${window.location.origin}/${imagePath.replace(/^\//, '')}` : '';

    const setHint = (text) => {
        if (! hint) {
            return;
        }
        hint.textContent = text || '';
        hint.hidden = ! text;
    };

    const openScheme = (scheme, label) => {
        if (! scheme) {
            return;
        }

        // Android Chrome: coba intent agar app terbuka dari browser.
        const isAndroid = /Android/i.test(navigator.userAgent || '');
        if (isAndroid && scheme.includes('://')) {
            const [proto, rest = ''] = scheme.split('://');
            const path = rest.replace(/^\/+/, '');
            const intent = `intent://${path}#Intent;scheme=${proto};end`;
            window.location.href = intent;
        } else {
            window.location.href = scheme;
        }

        setHint(
            `${label || 'Aplikasi'} dibuka (jika terpasang). Scan QR di atas, lalu kembali ke sini untuk upload bukti.`,
        );
    };

    box.querySelectorAll('[data-qris-open-app]').forEach((btn) => {
        btn.addEventListener('click', () => {
            openScheme(
                btn.getAttribute('data-qris-scheme') || '',
                btn.textContent?.trim() || 'Aplikasi',
            );
        });
    });

    const copyBtn = box.querySelector('[data-qris-copy]');
    copyBtn?.addEventListener('click', async () => {
        if (! payload) {
            setHint('Kode QRIS belum tersedia.');
            return;
        }
        try {
            await navigator.clipboard.writeText(payload);
            setHint('Kode QRIS disalin. Tempel di aplikasi pembayaran jika mendukung, atau scan QR di atas.');
        } catch {
            setHint('Gagal menyalin. Long-press QR atau gunakan tombol bagikan.');
        }
    });

    const shareBtn = box.querySelector('[data-qris-share]');
    shareBtn?.addEventListener('click', async () => {
        try {
            const file = imageUrl ? await qrisImageAsFile(imageUrl, root) : null;
            if (file && navigator.canShare?.({ files: [file] })) {
                await navigator.share({
                    files: [file],
                    title: 'QRIS pembayaran',
                    text: 'Scan QRIS ini untuk bayar pesanan.',
                });
                setHint('QR dibagikan. Selesai bayar, kembali ke sini lalu upload bukti.');
                return;
            }

            if (navigator.share && payload) {
                await navigator.share({
                    title: 'Kode QRIS',
                    text: payload,
                });
                setHint('Kode QRIS dibagikan. Selesai bayar, kembali upload bukti.');
                return;
            }

            if (payload) {
                await navigator.clipboard.writeText(payload);
                setHint('Perangkat tidak mendukung bagikan. Kode QRIS sudah disalin.');
                return;
            }

            setHint('Bagikan tidak tersedia di perangkat ini. Scan QR di atas dengan aplikasi e-wallet.');
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }
            setHint('Gagal membagikan. Scan QR di atas dengan aplikasi e-wallet/bank.');
        }
    });
}

async function qrisImageAsFile(imageUrl, root) {
    const img = root.querySelector('[data-qris-image]');
    try {
        if (img?.complete && img.naturalWidth > 0) {
            const canvas = document.createElement('canvas');
            const size = Math.max(img.naturalWidth, 480);
            canvas.width = size;
            canvas.height = size;
            const ctx = canvas.getContext('2d');
            if (ctx) {
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, size, size);
                ctx.drawImage(img, 0, 0, size, size);
                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
                if (blob) {
                    return new File([blob], 'qris-pembayaran.png', { type: 'image/png' });
                }
            }
        }
    } catch {
        // fallback fetch di bawah
    }

    try {
        const response = await fetch(imageUrl, { credentials: 'same-origin' });
        if (! response.ok) {
            return null;
        }
        const blob = await response.blob();
        const type = blob.type || 'image/svg+xml';
        const ext = type.includes('svg') ? 'svg' : 'png';
        return new File([blob], `qris-pembayaran.${ext}`, { type });
    } catch {
        return null;
    }
}

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
    // Shared hosting: poll jarang + stop otomatis agar tidak mengisi EP.
    const baseIntervalSec = Math.max(
        180,
        Number(section.dataset.orderPollInterval || document.body?.dataset?.orderPollInterval || 180),
    );
    const maxPollMs = 15 * 60 * 1000; // stop setelah 15 menit
    const startedAt = Date.now();
    let intervalSec = baseIntervalSec;
    let inFlight = false;
    let timer = null;
    let stopped = false;
    let failStreak = 0;

    const schedule = () => {
        if (stopped) {
            return;
        }
        if (timer) {
            window.clearTimeout(timer);
        }
        timer = window.setTimeout(tick, intervalSec * 1000);
    };

    const poll = async () => {
        if (inFlight || stopped) {
            return;
        }
        if (Date.now() - startedAt > maxPollMs) {
            stopped = true;
            return;
        }
        inFlight = true;

        try {
            const response = await fetch(statusUrl, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.status === 503 || response.status === 429 || response.status === 508 || ! response.ok) {
                failStreak += 1;
                intervalSec = Math.min(300, Math.round(intervalSec * 2));
                if (failStreak >= 2 && typeof window.showServerBusy === 'function') {
                    window.showServerBusy({
                        force: true,
                        message: 'Server sedang penuh. Muat ulang halaman untuk mencoba lagi.',
                    });
                }
                return;
            }

            failStreak = 0;
            intervalSec = baseIntervalSec;
            const data = await response.json();

            if (data.is_served || data.is_paid || (initialStatus && data.status !== initialStatus)) {
                stopped = true;
                window.location.reload();
            }
        } catch {
            failStreak += 1;
            intervalSec = Math.min(300, Math.round(intervalSec * 2));
            if (failStreak >= 2 && typeof window.showServerBusy === 'function') {
                window.showServerBusy({
                    force: true,
                    message: 'Koneksi gagal. Muat ulang halaman untuk mencoba lagi.',
                });
            }
        } finally {
            inFlight = false;
        }
    };

    const tick = async () => {
        if (document.visibilityState === 'hidden') {
            schedule();
            return;
        }

        await poll();
        schedule();
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && !stopped) {
            intervalSec = baseIntervalSec;
            tick();
        }
    });

    tick();
}
