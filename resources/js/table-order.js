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

async function compressProofImage(file) {
    if (! file) {
        return null;
    }

    const type = (file.type || '').toLowerCase();
    const alreadySmall = file.size <= 1.5 * 1024 * 1024
        && (type === 'image/jpeg' || type === 'image/jpg' || type === 'image/png' || type === 'image/webp');
    if (alreadySmall) {
        return file;
    }

    const bitmap = await loadProofBitmap(file);
    if (! bitmap) {
        return file.size <= 10 * 1024 * 1024 ? file : null;
    }

    const maxSide = 1600;
    const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
    const width = Math.max(1, Math.round(bitmap.width * scale));
    const height = Math.max(1, Math.round(bitmap.height * scale));
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (! ctx) {
        return file;
    }
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, width, height);
    ctx.drawImage(bitmap, 0, 0, width, height);
    if (typeof bitmap.close === 'function') {
        bitmap.close();
    }

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.82));
    if (! blob) {
        return file;
    }

    return new File([blob], 'bukti-pembayaran.jpg', { type: 'image/jpeg' });
}

async function loadProofBitmap(file) {
    if (typeof createImageBitmap === 'function') {
        try {
            return await createImageBitmap(file);
        } catch {
            // HEIC / beberapa galeri Android gagal di sini.
        }
    }

    const url = URL.createObjectURL(file);
    try {
        const image = await new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => reject(new Error('load failed'));
            img.src = url;
        });
        return image;
    } catch {
        return null;
    } finally {
        URL.revokeObjectURL(url);
    }
}

async function submitProofWithFetch(form, file, submitBtn, showProofError) {
    if (! (form instanceof HTMLFormElement) || ! file) {
        return;
    }

    if (submitBtn) {
        submitBtn.disabled = true;
    }

    try {
        const body = new FormData(form);
        body.set('payment_proof', file, file.name || 'bukti-pembayaran.jpg');
        const token = form.querySelector('input[name="_token"]')?.value;
        const response = await fetch(form.action, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            redirect: 'manual',
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
        });

        const location = response.headers.get('Location');
        if (location) {
            window.location.href = location;
            return;
        }

        if (response.type === 'opaqueredirect' || response.status === 0 || response.status === 301 || response.status === 302) {
            window.location.href = form.action.replace(/\/bayar.*$/, '') + '#ke-kasir';
            return;
        }

        if (response.ok) {
            window.location.reload();
            return;
        }

        showProofError?.('Gagal mengunggah bukti. Coba foto lebih kecil, atau pilih ulang dari galeri.');
    } catch {
        showProofError?.('Gagal mengunggah bukti. Periksa koneksi lalu coba lagi.');
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
        }
    }
}

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
    const proofError = root.querySelector('[data-order-proof-error]');
    const submitBtn = root.querySelector('[data-order-qris-pay-submit]');
    const pickers = root.querySelectorAll('[data-order-payment-proof-pick]');
    let objectUrl = null;
    let selectedProof = null;

    const showProofError = (text) => {
        if (! proofError) {
            return;
        }
        proofError.textContent = text || '';
        proofError.classList.toggle('hidden', ! text);
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

    const showProofPreview = (file) => {
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
    };

    const handlePickedProof = async (file) => {
        if (! file) {
            return;
        }
        showProofError('');
        selectedProof = file;
        showProofPreview(file);

        try {
            const compressed = await compressProofImage(file);
            if (compressed) {
                selectedProof = compressed;
                assignProofToInput(compressed);
                showProofPreview(compressed);
            } else {
                assignProofToInput(file);
            }
        } catch {
            assignProofToInput(file);
        }
    };

    pickers.forEach((picker) => {
        picker.addEventListener('change', () => {
            const file = picker.files?.[0];
            handlePickedProof(file);
            picker.value = '';
        });
    });

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
        if (file) {
            handlePickedProof(file);
        }
    });

    form?.addEventListener('submit', (event) => {
        const file = selectedProof || proofInput?.files?.[0];
        if (! file) {
            event.preventDefault();
            showProofError('Pilih bukti dari galeri atau ambil foto dulu.');
            return;
        }
        if (! window.confirm('Kirim bukti pembayaran? Pesanan akan dicatat lunas dan masuk ke kasir.')) {
            event.preventDefault();
            return;
        }

        if (! proofInput?.files?.length) {
            event.preventDefault();
            submitProofWithFetch(form, file, submitBtn, showProofError);
        }
    });

    const cashForm = root.querySelector('[data-order-cash-send-form]');
    cashForm?.addEventListener('submit', (event) => {
        if (! window.confirm('Kirim pesanan ke kasir untuk bayar tunai?')) {
            event.preventDefault();
        }
    });

    initOrderQrisSaveGallery(root);
    showMethod('qris');
}

function initOrderQrisSaveGallery(root) {
    const box = root.querySelector('[data-order-qris-save]');
    const button = box?.querySelector('[data-qris-save-gallery]');
    if (! box || ! button) {
        return;
    }

    const hint = box.querySelector('[data-qris-save-hint]');
    const filename = box.getAttribute('data-qris-filename') || 'qris-pembayaran.png';
    const imageUrl = box.getAttribute('data-qris-image-url') || '';

    const setHint = (text) => {
        if (! hint) {
            return;
        }
        hint.textContent = text || '';
        hint.hidden = ! text;
    };

    button.addEventListener('click', async () => {
        button.disabled = true;
        setHint('Menyiapkan gambar…');

        try {
            const file = await qrisImageAsPngFile(root, imageUrl, filename);
            if (! file) {
                throw new Error('Gambar QRIS tidak tersedia.');
            }

            // Unduh PNG — di Android biasanya masuk Downloads/Galeri.
            downloadBlobFile(file, filename);
            setHint('QRIS tersimpan. Cek Galeri atau folder Downloads, lalu bayar lewat e-wallet/bank.');
        } catch {
            setHint('Gagal menyimpan. Long-press gambar QR di atas, lalu pilih Simpan gambar.');
        } finally {
            button.disabled = false;
        }
    });
}

function downloadBlobFile(file, filename) {
    const url = URL.createObjectURL(file);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.rel = 'noopener';
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 2000);
}

async function qrisImageAsPngFile(root, imageUrl, filename) {
    const img = root.querySelector('[data-qris-image]');

    const drawToPng = async (source) => {
        const canvas = document.createElement('canvas');
        const size = 720;
        canvas.width = size;
        canvas.height = size;
        const ctx = canvas.getContext('2d');
        if (! ctx) {
            return null;
        }
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, size, size);
        ctx.drawImage(source, 0, 0, size, size);
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        if (! blob) {
            return null;
        }
        return new File([blob], filename, { type: 'image/png' });
    };

    if (img) {
        try {
            if (! img.complete || img.naturalWidth === 0) {
                await new Promise((resolve, reject) => {
                    img.onload = () => resolve();
                    img.onerror = () => reject(new Error('load failed'));
                    window.setTimeout(() => resolve(), 1500);
                });
            }
            if (img.naturalWidth > 0) {
                const fromImg = await drawToPng(img);
                if (fromImg) {
                    return fromImg;
                }
            }
        } catch {
            // fallback fetch
        }
    }

    if (! imageUrl) {
        return null;
    }

    const response = await fetch(imageUrl, { credentials: 'same-origin' });
    if (! response.ok) {
        return null;
    }

    const blob = await response.blob();
    if ((blob.type || '').includes('png')) {
        return new File([blob], filename, { type: 'image/png' });
    }

    const objectUrl = URL.createObjectURL(blob);
    try {
        const loaded = await loadImageElement(objectUrl);
        return await drawToPng(loaded);
    } finally {
        URL.revokeObjectURL(objectUrl);
    }
}

function loadImageElement(src) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.crossOrigin = 'anonymous';
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('image load failed'));
        image.src = src;
    });
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

            // Hanya reload jika status benar-benar berubah.
            // Jangan pakai data.is_paid saja: halaman "Menunggu diantar" sudah paid,
            // sehingga is_paid=true akan memicu refresh berulang.
            if (initialStatus && data.status && data.status !== initialStatus) {
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
