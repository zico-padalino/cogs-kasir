/**
 * Notifikasi kasir — polling pesanan online + toast visual + auto load.
 */
import { refreshKasirOrderUi, initItemDeliverToggle } from './kasir';

let knownOrderIds = null;
let isHandlingNewOrder = false;
let deferredOrderAlert = false;
let wasTransactionActive = false;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function showKasirToast(message) {
    const existing = document.querySelector('[data-kasir-toast]');
    existing?.remove();

    const toast = document.createElement('div');
    toast.className = 'kasir-toast';
    toast.setAttribute('data-kasir-toast', '');
    toast.setAttribute('role', 'status');

    const icon = document.createElement('span');
    icon.className = 'kasir-toast-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '🔔';

    const text = document.createElement('span');
    text.className = 'kasir-toast-text';
    text.textContent = message;

    toast.append(icon, text);
    document.body.append(toast);

    window.requestAnimationFrame(() => {
        toast.classList.add('is-visible');
    });
}

function alertNewOrder(toast) {
    if (toast) {
        showKasirToast(toast);
    }
}

function updatePendingPanel(html) {
    const wrap = document.querySelector('[data-pos-pending-wrap]');
    if (! wrap) {
        return;
    }

    wrap.innerHTML = html;
    // Pastikan toggle antrian + ceklis antar tetap hidup setelah HTML diganti polling.
    const root = document.getElementById('kasir-pos');
    if (root) {
        initItemDeliverToggle();
    }
}

function flashPendingPanel() {
    const pending = document.querySelector('[data-pos-pending]');
    if (! pending) {
        return;
    }

    pending.classList.add('is-new-alert', 'is-expanded');
    const toggle = pending.querySelector('[data-pos-pending-toggle]');
    toggle?.setAttribute('aria-expanded', 'true');

    window.setTimeout(() => {
        pending.classList.remove('is-new-alert');
    }, 2800);
}

async function loadOrderIntoKasir(orderId) {
    const token = csrfToken();
    if (! token || ! orderId) {
        return null;
    }

    const formData = new FormData();
    formData.append('_token', token);

    const response = await fetch(`/kasir/load-order/${orderId}`, {
        method: 'POST',
        body: formData,
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    if (! response.ok) {
        return null;
    }

    return response.json();
}

function hasActiveKasirDraftWithItems() {
    const root = document.getElementById('kasir-pos');
    if (! root) {
        return false;
    }

    const isOnlineConfirm = Boolean(root.querySelector('[data-pos-receipt-confirm]'));
    const itemCount = root.querySelectorAll('[data-kasir-item]').length;

    return itemCount > 0 && ! isOnlineConfirm;
}

function isKasirTransactionActive() {
    const root = document.getElementById('kasir-pos');
    if (! root) {
        return false;
    }

    if (root.querySelector('[data-kasir-pay-modal]:not(.hidden)')) {
        return true;
    }

    if (root.querySelector('[data-kasir-confirm-modal]:not(.hidden)')) {
        return true;
    }

    return root.querySelectorAll('[data-kasir-item]').length > 0;
}

function flushDeferredOrderAlertIfIdle() {
    const busy = isKasirTransactionActive();

    if (wasTransactionActive && ! busy && deferredOrderAlert) {
        alertNewOrder('Ada pesanan baru menunggu — buka dari banner atas');
        flashPendingPanel();
        deferredOrderAlert = false;
    }

    wasTransactionActive = busy;
}

function observeKasirTransactionState() {
    const root = document.getElementById('kasir-pos');
    if (! root || root.dataset.kasirTransactionObserver === '1') {
        return;
    }

    root.dataset.kasirTransactionObserver = '1';
    wasTransactionActive = isKasirTransactionActive();

    const observer = new MutationObserver(() => {
        flushDeferredOrderAlertIfIdle();
    });

    observer.observe(root, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'hidden'],
    });
}

async function handleIncomingOrders(newIds, data, shell, currentIds) {
    if (isHandlingNewOrder || newIds.length === 0) {
        return;
    }

    if (isKasirTransactionActive()) {
        deferredOrderAlert = true;
        updatePendingPanel(data.html ?? '');
        knownOrderIds = currentIds;
        flushDeferredOrderAlertIfIdle();

        return;
    }

    isHandlingNewOrder = true;

    const autoLoadWanted = shell.dataset.kasirAutoLoad !== '0';
    const preserveKasirDraft = hasActiveKasirDraftWithItems();
    const autoLoad = autoLoadWanted && ! preserveKasirDraft;
    const orderId = newIds.includes(Number(data.latest_order_id))
        ? Number(data.latest_order_id)
        : Math.max(...newIds);

    alertNewOrder(preserveKasirDraft
        ? 'Pesanan online baru masuk — cek banner atas, lanjutkan transaksi kasir dulu'
        : (autoLoad
            ? 'Pesanan baru masuk ke kasir'
            : 'Pesanan baru menunggu — buka dari daftar online'));

    updatePendingPanel(data.html ?? '');
    flashPendingPanel();

    if (autoLoad && orderId) {
        try {
            const payload = await loadOrderIntoKasir(orderId);
            if (payload && refreshKasirOrderUi(payload)) {
                window.setTimeout(() => {
                    isHandlingNewOrder = false;
                }, 300);

                return;
            }
        } catch {
            //
        }

        const indexUrl = shell.dataset.kasirIndexUrl || '/kasir';
        const target = new URL(indexUrl, window.location.origin);
        target.searchParams.set('tab', 'cart');
        window.location.assign(target.toString());

        return;
    }

    window.setTimeout(() => {
        isHandlingNewOrder = false;
    }, 300);
}

function isPinManagementPage() {
    const path = (window.location.pathname || '').replace(/\/+$/, '') || '/';

    return path === '/pin';
}

async function pollPendingOrders(pollUrl, shell) {
    const pinPollOnly = isPinPollOnly(shell);

    const response = await fetch(pollUrl, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        redirect: 'manual',
    });

    if (shouldForcePinLock(response)) {
        if (! pinPollOnly) {
            goToPinUnlock(shell);
        }

        return;
    }

    if (! response.ok) {
        return;
    }

    const data = await response.json();

    if (! pinPollOnly) {
        syncPinExpiryFromPayload(shell, data);

        if (data.unlocked === false) {
            goToPinUnlock(shell, data.redirect);

            return;
        }
    }

    const currentIds = new Set((data.order_ids ?? []).map((id) => Number(id)));
    const notifyIds = new Set((data.notify_order_ids ?? data.order_ids ?? []).map((id) => Number(id)));

    if (knownOrderIds === null) {
        knownOrderIds = currentIds;

        if (! pinPollOnly) {
            updatePendingPanel(data.html ?? '');
        }

        return;
    }

    const newIds = [...notifyIds].filter((id) => ! knownOrderIds.has(id));

    if (newIds.length > 0) {
        if (pinPollOnly) {
            alertNewOrder('Pesanan baru masuk — masukkan PIN untuk membuka kasir');
            knownOrderIds = currentIds;

            return;
        }

        await handleIncomingOrders(newIds, data, shell, currentIds);
    } else if (! pinPollOnly && currentIds.size !== knownOrderIds.size) {
        updatePendingPanel(data.html ?? '');
        knownOrderIds = currentIds;
    } else {
        knownOrderIds = currentIds;
    }

    flushDeferredOrderAlertIfIdle();
}

function shouldForcePinLock(response) {
    if (response.status === 423) {
        return true;
    }

    if (response.type === 'opaqueredirect') {
        return true;
    }

    if (response.status >= 300 && response.status < 400) {
        const location = response.headers.get('Location') || '';
        return location.includes('/kasir/pin');
    }

    return false;
}

function goToPinUnlock(shell, redirectUrl) {
    if (isPinUnlockPage()) {
        return;
    }

    if (isPinManagementPage()) {
        return;
    }

    const path = window.location.pathname || '';
    if (path.includes('/kasir/pin')) {
        return;
    }

    const url = redirectUrl || shell?.dataset?.kasirPinUnlockUrl || '/kasir/pin';
    window.location.assign(url);
}

function isPinUnlockPage() {
    const path = (window.location.pathname || '').replace(/\/+$/, '') || '/';

    return path.endsWith('/kasir/pin') || path === '/kasir/pin';
}

function isPinPollOnly(shell) {
    return shell?.dataset?.kasirPinPollOnly === '1' || isPinUnlockPage();
}

let pinExpiryTimer = null;
let pinStatusTimer = null;
let pinTouchInFlight = false;
let lastPinTouchAt = 0;
const PIN_TOUCH_THROTTLE_MS = 15_000;

function syncPinExpiryFromPayload(shell, data) {
    if (! data || typeof data.remaining_seconds !== 'number') {
        return;
    }

    if (data.unlocked === false || data.remaining_seconds <= 0) {
        goToPinUnlock(shell, data.redirect);
        return;
    }

    schedulePinExpiryRedirect(shell, data.remaining_seconds, data.server_now, data.expires_at);
}

function schedulePinExpiryRedirect(shell, remainingSeconds, serverNow, expiresAt) {
    const unlockUrl = shell.dataset.kasirPinUnlockUrl || '/kasir/pin';
    let delayMs;

    if (typeof remainingSeconds === 'number' && Number.isFinite(remainingSeconds)) {
        delayMs = Math.max(0, remainingSeconds) * 1000;
    } else {
        const expires = parseInt(expiresAt || shell.dataset.kasirPinExpiresAt || '', 10);
        const server = parseInt(serverNow || shell.dataset.kasirServerNow || '', 10);
        const client = Math.floor(Date.now() / 1000);

        if (! expires) {
            return;
        }

        const offset = Number.isFinite(server) ? (server - client) : 0;
        const remaining = expires - (client + offset);
        delayMs = Math.max(0, remaining) * 1000;
    }

    if (pinExpiryTimer) {
        window.clearTimeout(pinExpiryTimer);
    }

    pinExpiryTimer = window.setTimeout(() => {
        goToPinUnlock(shell, unlockUrl);
    }, delayMs + 300);
}

function resetLocalIdleTimer(shell) {
    const ttlMinutes = Math.max(1, parseInt(shell.dataset.kasirPinTtlMinutes || '15', 10));
    schedulePinExpiryRedirect(shell, ttlMinutes * 60);
}

async function touchPinSession(shell, { force = false } = {}) {
    const touchUrl = shell.dataset.kasirPinTouchUrl;
    if (! touchUrl || isPinUnlockPage() || isPinManagementPage()) {
        return;
    }

    const now = Date.now();
    if (! force && (pinTouchInFlight || now - lastPinTouchAt < PIN_TOUCH_THROTTLE_MS)) {
        return;
    }

    pinTouchInFlight = true;
    lastPinTouchAt = now;

    try {
        const response = await fetch(touchUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: '{}',
        });

        if (shouldForcePinLock(response)) {
            goToPinUnlock(shell);
            return;
        }

        if (! response.ok) {
            return;
        }

        const data = await response.json();
        syncPinExpiryFromPayload(shell, data);
    } catch {
        //
    } finally {
        pinTouchInFlight = false;
    }
}

function initKasirIdlePinGuard(shell) {
    if (isPinUnlockPage() || isPinManagementPage() || isPinPollOnly(shell)) {
        return;
    }

    let activityQueued = false;

    const onUserActivity = () => {
        if (document.visibilityState === 'hidden') {
            return;
        }

        // Reset timer lokal segera agar sentuhan terasa langsung.
        resetLocalIdleTimer(shell);

        if (activityQueued) {
            return;
        }

        activityQueued = true;
        window.setTimeout(() => {
            activityQueued = false;
            touchPinSession(shell);
        }, 400);
    };

    ['pointerdown', 'touchstart', 'keydown', 'click'].forEach((eventName) => {
        document.addEventListener(eventName, onUserActivity, { passive: true, capture: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            pollPinStatus(shell);
        }
    });
}

async function pollPinStatus(shell) {
    const statusUrl = shell.dataset.kasirPinStatusUrl;
    if (! statusUrl) {
        return;
    }

    try {
        const response = await fetch(statusUrl, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            redirect: 'manual',
        });

        if (! response.ok) {
            if (response.status === 401 || response.status === 419) {
                return;
            }
            return;
        }

        const data = await response.json();
        syncPinExpiryFromPayload(shell, data);
    } catch {
        //
    }
}

function openCartTabFromQuery() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('tab') !== 'cart') {
        return;
    }

    const root = document.getElementById('kasir-pos');
    const cartTab = root?.querySelector('[data-kasir-tab="cart"]');
    cartTab?.click();

    params.delete('tab');
    const nextQuery = params.toString();
    const nextUrl = `${window.location.pathname}${nextQuery ? `?${nextQuery}` : ''}${window.location.hash}`;
    window.history.replaceState({}, '', nextUrl);
}

function initKasirNotifications() {
    const shell = document.querySelector('[data-kasir-notifications]');
    if (! shell) {
        return;
    }

    if (isPinManagementPage()) {
        return;
    }

    const pollUrl = shell.dataset.kasirPollUrl;
    const intervalSeconds = Math.max(5, parseInt(shell.dataset.kasirPollInterval || '5', 10));
    const pinPollOnly = isPinPollOnly(shell);

    if (! pinPollOnly) {
        openCartTabFromQuery();
        schedulePinExpiryRedirect(shell);
        pollPinStatus(shell);
        initKasirIdlePinGuard(shell);
        observeKasirTransactionState();

        if (pinStatusTimer) {
            window.clearInterval(pinStatusTimer);
        }
        pinStatusTimer = window.setInterval(() => pollPinStatus(shell), 20_000);
    }

    if (! pollUrl) {
        return;
    }

    const runPoll = () => {
        if (isHandlingNewOrder) {
            return;
        }

        pollPendingOrders(pollUrl, shell).catch(() => {
            //
        });
    };

    runPoll();
    window.setInterval(runPoll, intervalSeconds * 1000);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && ! isHandlingNewOrder) {
            runPoll();
            pollPinStatus(shell);
        }
    });
}

document.addEventListener('DOMContentLoaded', initKasirNotifications);
