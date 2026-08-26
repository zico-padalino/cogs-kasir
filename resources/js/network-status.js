const DB_NAME = 'cogs-kasir-offline';
const DB_VERSION = 1;
const STORE_NAME = 'requests';

function openQueue() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);
        request.onupgradeneeded = () => request.result.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function queueRequest(form) {
    const formData = new FormData(form);
    const entries = [];

    for (const [name, value] of formData.entries()) {
        if (typeof value !== 'string') {
            throw new Error('Bukti pembayaran foto belum dapat disimpan saat offline.');
        }
        entries.push([name, value]);
    }

    const method = entries.find(([name]) => name === '_method')?.[1] || 'POST';
    const db = await openQueue();
    await new Promise((resolve, reject) => {
        const request = db.transaction(STORE_NAME, 'readwrite').objectStore(STORE_NAME).add({
            url: form.action,
            method,
            entries,
            createdAt: new Date().toISOString(),
        });
        request.onsuccess = resolve;
        request.onerror = () => reject(request.error);
    });
}

async function getQueuedRequests() {
    const db = await openQueue();
    return new Promise((resolve, reject) => {
        const request = db.transaction(STORE_NAME).objectStore(STORE_NAME).getAll();
        request.onsuccess = () => resolve(request.result.sort((a, b) => a.id - b.id));
        request.onerror = () => reject(request.error);
    });
}

async function removeQueuedRequest(id) {
    const db = await openQueue();
    await new Promise((resolve, reject) => {
        const request = db.transaction(STORE_NAME, 'readwrite').objectStore(STORE_NAME).delete(id);
        request.onsuccess = resolve;
        request.onerror = () => reject(request.error);
    });
}

function makeBody(entries) {
    const body = new URLSearchParams();
    entries.forEach(([name, value]) => body.append(name, value));
    return body;
}

function renderStatus(element, online, count = 0) {
    element.classList.toggle('is-online', online && count === 0);
    element.classList.toggle('is-offline', !online);
    element.classList.toggle('is-syncing', online && count > 0);
    element.textContent = !online
        ? 'Offline — transaksi akan disinkronkan saat online'
        : count > 0
            ? `Menyinkronkan ${count} transaksi...`
            : 'Online';
}

async function countQueuedRequests() {
    try {
        return (await getQueuedRequests()).length;
    } catch {
        return 0;
    }
}

async function syncQueue(element) {
    if (!navigator.onLine) {
        renderStatus(element, false, await countQueuedRequests());
        return;
    }

    const requests = await getQueuedRequests();
    renderStatus(element, true, requests.length);

    for (const queued of requests) {
        try {
            const response = await fetch(queued.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: makeBody(queued.entries),
            });
            if (!response.ok) {
                break;
            }
            await removeQueuedRequest(queued.id);
        } catch {
            break;
        }
    }

    const remaining = await countQueuedRequests();
    renderStatus(element, navigator.onLine, remaining);
    if (remaining === 0 && requests.length > 0) {
        window.location.reload();
    }
}

function isKasirForm(form) {
    const url = new URL(form.action, window.location.origin);
    if (url.origin !== window.location.origin || !url.pathname.startsWith('/kasir/')) return false;

    return /\/kasir\/(orders\/new|orders\/current|orders\/discount|orders\/cancel|orders\/[^/]+\/(load|edit|confirm|cancel)|items(?:\/[^/]+)?|pay|open-bill)$/.test(url.pathname);
}

function initNetworkStatus() {
    const element = document.querySelector('[data-network-status]');
    if (!element) return;

    const update = () => void syncQueue(element).catch(() => renderStatus(element, navigator.onLine));
    window.addEventListener('online', update);
    window.addEventListener('offline', update);

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !isKasirForm(form) || navigator.onLine) return;

        const paymentMethod = form.querySelector('[name="payment_method"]:checked')?.value;
        if (paymentMethod && paymentMethod !== 'cash') {
            event.preventDefault();
            window.alert('QRIS dan transfer membutuhkan koneksi. Gunakan pembayaran tunai saat offline.');
            return;
        }

        event.preventDefault();
        try {
            await queueRequest(form);
            renderStatus(element, false, await countQueuedRequests());
            window.alert('Koneksi sedang offline. Transaksi disimpan dan akan disinkronkan otomatis saat online.');
            form.closest('[data-kasir-pay-modal]')?.classList.add('hidden');
            form.closest('[data-kasir-modal]')?.classList.add('hidden');
        } catch (error) {
            window.alert(error.message || 'Transaksi belum dapat disimpan offline.');
        }
    });

    void syncQueue(element);
}

document.addEventListener('DOMContentLoaded', initNetworkStatus);