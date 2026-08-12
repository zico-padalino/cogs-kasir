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

let lastSpeakAt = 0;
let lastPushWakeAt = 0;
let audioUnlocked = false;
let audioContext = null;
let voiceAudio = null;

const ORDER_VOICE_URL = '/sounds/pesanan-masuk.mp3';
const ORDER_VOICE_TEXT = 'Pesanan masuk';

function getVoiceAudio() {
    if (! voiceAudio) {
        voiceAudio = new Audio(ORDER_VOICE_URL);
        voiceAudio.preload = 'auto';
        voiceAudio.volume = 1;
    }

    return voiceAudio;
}

function unlockKasirAudio() {
    audioUnlocked = true;

    try {
        if (! audioContext) {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (Ctx) {
                audioContext = new Ctx();
            }
        }
        if (audioContext?.state === 'suspended') {
            audioContext.resume();
        }

        const audio = getVoiceAudio();
        audio.muted = true;
        const play = audio.play();
        if (play && typeof play.then === 'function') {
            play.then(() => {
                audio.pause();
                audio.currentTime = 0;
                audio.muted = false;
            }).catch(() => {
                audio.muted = false;
            });
        } else {
            audio.muted = false;
        }

        if ('speechSynthesis' in window) {
            window.speechSynthesis.resume();
            window.speechSynthesis.getVoices();
        }
    } catch {
        //
    }
}

function playVoiceClip() {
    return new Promise((resolve) => {
        try {
            const audio = getVoiceAudio();
            audio.pause();
            audio.currentTime = 0;
            audio.muted = false;
            audio.volume = 1;

            const done = (ok) => {
                audio.onended = null;
                audio.onerror = null;
                resolve(ok);
            };

            audio.onended = () => done(true);
            audio.onerror = () => done(false);

            const play = audio.play();
            if (play && typeof play.catch === 'function') {
                play.catch(() => done(false));
            }
        } catch {
            resolve(false);
        }
    });
}

function speakWithBrowserTts(text) {
    if (! ('speechSynthesis' in window)) {
        return;
    }

    try {
        window.speechSynthesis.cancel();
        window.speechSynthesis.resume();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'id-ID';
        utterance.rate = 0.92;
        utterance.pitch = 1.05;
        utterance.volume = 1;

        const voices = window.speechSynthesis.getVoices?.() || [];
        const idVoice = voices.find((voice) => {
            const lang = (voice.lang || '').toLowerCase().replace('_', '-');
            return lang === 'id-id' || lang.startsWith('id') || lang.startsWith('in');
        });
        if (idVoice) {
            utterance.voice = idVoice;
        }

        window.speechSynthesis.speak(utterance);
    } catch {
        //
    }
}

async function speakNewOrder(text = ORDER_VOICE_TEXT) {
    const now = Date.now();
    if (now - lastSpeakAt < 3500) {
        return;
    }
    lastSpeakAt = now;

    const first = await playVoiceClip();
    if (first) {
        window.setTimeout(() => {
            playVoiceClip();
        }, 280);
        return;
    }

    speakWithBrowserTts(text || ORDER_VOICE_TEXT);
}

function showKasirBrowserNotification(title, body) {
    if (! ('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }

    // Push lewat service worker sudah menampilkan notifikasi OS.
    if (Date.now() - lastPushWakeAt < 5000) {
        return;
    }

    try {
        const notification = new Notification(title, {
            body,
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            tag: 'kasir-new-order',
            renotify: true,
            silent: true,
        });
        notification.onclick = () => {
            window.focus();
            notification.close();
            const indexUrl = document.body?.dataset?.kasirIndexUrl || '/kasir';
            if (! window.location.pathname.startsWith('/kasir')) {
                window.location.assign(indexUrl);
            }
        };
    } catch {
        //
    }
}

function alertNewOrder(toast, options = {}) {
    const title = options.title || 'Pesanan baru masuk';
    const body = options.body || toast || 'Ada pesanan online baru. Buka kasir untuk memproses.';
    const speakText = options.speakText || ORDER_VOICE_TEXT;

    if (toast) {
        showKasirToast(toast);
    }

    showKasirBrowserNotification(title, body);
    speakNewOrder(speakText);
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
        alertNewOrder('Ada pesanan baru menunggu — buka dari banner atas', {
            speakText: ORDER_VOICE_TEXT,
        });
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
            : 'Pesanan baru menunggu — buka dari daftar online'), {
        title: 'Pesanan baru masuk',
        body: preserveKasirDraft
            ? 'Ada pesanan online baru. Selesaikan transaksi kasir dulu, lalu buka dari antrian.'
            : 'Ada pesanan online baru. Buka kasir untuk memproses.',
        speakText: ORDER_VOICE_TEXT,
    });

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
        const error = new Error(`poll_failed_${response.status}`);
        error.status = response.status;
        throw error;
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
            alertNewOrder('Pesanan baru masuk — masukkan PIN untuk membuka kasir', {
                speakText: ORDER_VOICE_TEXT,
            });
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
/** Hemat EP: touch server jarang; expiry lokal yang utama. */
const PIN_TOUCH_THROTTLE_MS = 120_000;

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
    const ttlMinutes = Math.max(1, parseInt(shell.dataset.kasirPinTtlMinutes || '20', 10));
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
    let intervalSeconds = Math.max(30, parseInt(shell.dataset.kasirPollInterval || '60', 10));
    const continuousPoll = shell.dataset.kasirContinuousPoll === '1';
    const pinPollOnly = isPinPollOnly(shell);
    let pollTimer = null;
    let pollInFlight = false;

    if (! pinPollOnly) {
        openCartTabFromQuery();
        schedulePinExpiryRedirect(shell);
        // Satu sync saat buka halaman — tanpa interval berkala (hemat EP/NPROC).
        pollPinStatus(shell);
        initKasirIdlePinGuard(shell);
        observeKasirTransactionState();

        if (pinStatusTimer) {
            window.clearInterval(pinStatusTimer);
            pinStatusTimer = null;
        }
    }

    if (! pollUrl) {
        return;
    }

    const schedulePoll = () => {
        if (! continuousPoll) {
            return;
        }
        if (pollTimer) {
            window.clearTimeout(pollTimer);
        }
        pollTimer = window.setTimeout(runPoll, intervalSeconds * 1000);
    };

    const runPoll = () => {
        if (isHandlingNewOrder || pollInFlight) {
            schedulePoll();
            return;
        }

        if (document.visibilityState === 'hidden') {
            schedulePoll();
            return;
        }

        pollInFlight = true;
        pollPendingOrders(pollUrl, shell)
            .then(() => {
                intervalSeconds = Math.max(30, parseInt(shell.dataset.kasirPollInterval || '60', 10));
            })
            .catch((err) => {
                const status = err?.status;
                if (status === 503 || status === 429 || (status >= 500)) {
                    intervalSeconds = Math.min(120, Math.round(intervalSeconds * 2));
                }
            })
            .finally(() => {
                pollInFlight = false;
                schedulePoll();
            });
    };

    /** Pull sekali — dipanggil saat web push / tab fokus. */
    const pullOnce = () => {
        if (isHandlingNewOrder || pollInFlight) {
            return;
        }
        if (document.visibilityState === 'hidden') {
            return;
        }
        pollInFlight = true;
        pollPendingOrders(pollUrl, shell)
            .catch(() => {})
            .finally(() => {
                pollInFlight = false;
            });
    };

    window.__kasirPullPending = pullOnce;
    getVoiceAudio();

    const requestKasirNotifyPermission = async () => {
        if (! ('Notification' in window) || Notification.permission !== 'default') {
            return;
        }
        try {
            await Notification.requestPermission();
        } catch {
            //
        }
    };

    const onFirstKasirGesture = () => {
        unlockKasirAudio();
        requestKasirNotifyPermission();
        document.removeEventListener('pointerdown', onFirstKasirGesture, true);
        document.removeEventListener('keydown', onFirstKasirGesture, true);
    };

    document.addEventListener('pointerdown', onFirstKasirGesture, { capture: true, passive: true });
    document.addEventListener('keydown', onFirstKasirGesture, { capture: true });

    if ('Notification' in window && Notification.permission === 'default' && ! pinPollOnly) {
        const prompt = document.createElement('button');
        prompt.type = 'button';
        prompt.className = 'kasir-notify-prompt';
        prompt.setAttribute('data-kasir-notify-prompt', '');
        prompt.textContent = 'Aktifkan notifikasi & suara pesanan baru';
        prompt.addEventListener('click', async () => {
            unlockKasirAudio();
            await requestKasirNotifyPermission();
            prompt.remove();
        });
        document.body.append(prompt);
    }

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event?.data?.type !== 'kasir-wake') {
                return;
            }
            const reason = event.data?.reason || event.data?.data?.type;
            if (reason === 'new_order' || event.data?.data?.type === 'new_order' || ! reason) {
                lastPushWakeAt = Date.now();
                speakNewOrder(ORDER_VOICE_TEXT);
            }
        });
    }

    // Seed antrian sekali saat buka kasir (bukan loop kecuali continuous poll ON).
    if (continuousPoll) {
        runPoll();
    } else {
        pullOnce();
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && ! isHandlingNewOrder) {
            if (continuousPoll) {
                intervalSeconds = Math.max(30, parseInt(shell.dataset.kasirPollInterval || '60', 10));
                runPoll();
            } else {
                pullOnce();
            }
            if (! pinPollOnly) {
                pollPinStatus(shell);
            }
        }
    });

    window.addEventListener('kasir:pull-pending', pullOnce);
}

document.addEventListener('DOMContentLoaded', initKasirNotifications);
