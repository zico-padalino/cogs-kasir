/**
 * Notifikasi kasir — polling pesanan online + toast visual + auto load.
 */
import { refreshKasirOrderUi, initItemDeliverToggle } from './kasir';

let knownOrderIds = null;
let knownNotifyIds = null;
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
let audioContext = null;
let voiceAudio = null;
let pendingSpeakText = null;
let speakingInFlight = false;

const ORDER_VOICE_TEXT = 'Pesanan baru masuk';
const SPEAK_DEDUPE_MS = 3500;

function orderVoiceUrl() {
    return document.querySelector('[data-kasir-notifications]')?.dataset?.kasirVoiceUrl
        || '/sounds/pesanan-masuk.mp3';
}

function getVoiceAudio() {
    const url = orderVoiceUrl();

    if (! voiceAudio || voiceAudio.dataset?.srcUrl !== url) {
        voiceAudio = new Audio(url);
        voiceAudio.dataset.srcUrl = url;
        voiceAudio.preload = 'auto';
        voiceAudio.volume = 1;
        voiceAudio.setAttribute('playsinline', '');
        voiceAudio.playsInline = true;
    }

    return voiceAudio;
}

function hideKasirSoundPrompt() {
    document.querySelector('[data-kasir-notify-prompt]')?.remove();
}

function showKasirSoundPrompt() {
    if (document.querySelector('[data-kasir-notify-prompt]')) {
        return;
    }

    if (document.querySelector('[data-kasir-sound-enable]')) {
        return;
    }

    const prompt = document.createElement('button');
    prompt.type = 'button';
    prompt.className = 'kasir-notify-prompt';
    prompt.setAttribute('data-kasir-notify-prompt', '');
        prompt.textContent = 'Aktifkan notifikasi sistem (seperti WhatsApp)';
    prompt.addEventListener('click', () => {
        void enableKasirOrderAlerts();
    });
    document.body.append(prompt);
}

async function enableKasirOrderAlerts() {
    const buttons = document.querySelectorAll('[data-kasir-notify-prompt], [data-kasir-sound-enable]');
    buttons.forEach((button) => {
        button.disabled = true;
        button.textContent = 'Mengaktifkan notifikasi…';
    });

    await unlockKasirAudio();

    if ('Notification' in window && Notification.permission === 'default') {
        try {
            await Notification.requestPermission();
        } catch {
            //
        }
    }

    let pushResult = { ok: false };
    if (typeof window.__kasirInitPush === 'function') {
        try {
            pushResult = (await window.__kasirInitPush()) || { ok: false };
        } catch {
            pushResult = { ok: false };
        }
    }

    await showKasirSystemNotification(
        'Notifikasi kasir aktif',
        'Pesanan baru akan muncul di sini seperti WhatsApp, meski tab sedang di aplikasi lain.',
        { force: true, requireInteraction: true, tag: 'kasir-notify-test' },
    );

    const clipOk = await playVoiceClip();
    if (! clipOk) {
        playAttentionChime();
        await speakWithBrowserTts(ORDER_VOICE_TEXT);
    }

    hideKasirSoundPrompt();

    const denied = ('Notification' in window) && Notification.permission === 'denied';
    const label = denied
        ? 'Notifikasi diblokir — buka gembok URL browser, pilih Izinkan'
        : (pushResult.ok
            ? 'Notifikasi sistem aktif — ketuk untuk tes ulang'
            : 'Suara aktif — ketuk untuk tes notifikasi lagi');

    document.querySelectorAll('[data-kasir-sound-enable]').forEach((button) => {
        button.disabled = false;
        button.textContent = label;
    });
}

async function resumeAudioContext() {
    try {
        if (! audioContext) {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (Ctx) {
                audioContext = new Ctx();
            }
        }
        if (audioContext?.state === 'suspended') {
            await audioContext.resume();
        }
    } catch {
        //
    }

    return audioContext;
}

function playSilentUnlockBuffer(ctx) {
    if (! ctx) {
        return;
    }

    try {
        const buffer = ctx.createBuffer(1, 1, ctx.sampleRate || 22050);
        const source = ctx.createBufferSource();
        source.buffer = buffer;
        source.connect(ctx.destination);
        source.start(0);
    } catch {
        //
    }
}

let audioKeepAlive = null;

function startKasirAudioKeepAlive() {
    if (audioKeepAlive || ! audioContext) {
        return;
    }

    try {
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();
        oscillator.frequency.value = 40;
        gain.gain.value = 0.00008;
        oscillator.connect(gain);
        gain.connect(audioContext.destination);
        oscillator.start();
        audioKeepAlive = { oscillator, gain };
    } catch {
        //
    }
}

function playAttentionChime() {
    if (! audioContext) {
        return false;
    }

    try {
        const now = audioContext.currentTime;
        const notes = [880, 1175];
        notes.forEach((freq, index) => {
            const osc = audioContext.createOscillator();
            const gain = audioContext.createGain();
            const start = now + (index * 0.14);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, start);
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.22, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.2);
            osc.connect(gain);
            gain.connect(audioContext.destination);
            osc.start(start);
            osc.stop(start + 0.22);
        });

        return true;
    } catch {
        return false;
    }
}

async function unlockKasirAudio() {
    try {
        const ctx = await resumeAudioContext();
        playSilentUnlockBuffer(ctx);

        const audio = getVoiceAudio();
        audio.muted = true;
        try {
            await audio.play();
            audio.pause();
            audio.currentTime = 0;
        } catch {
            // File belum siap / autoplay — tetap lanjut TTS.
        }
        audio.muted = false;

        if ('speechSynthesis' in window) {
            window.speechSynthesis.resume();
            window.speechSynthesis.getVoices();
        }

        startKasirAudioKeepAlive();

        hideKasirSoundPrompt();

        if (pendingSpeakText) {
            const queued = pendingSpeakText;
            pendingSpeakText = null;
            await speakNewOrder(queued, { force: true });
        }
    } catch {
        //
    }
}

function playVoiceClip() {
    return new Promise((resolve) => {
        let settled = false;
        let timer = 0;

        try {
            const audio = getVoiceAudio();
            audio.pause();
            audio.currentTime = 0;
            audio.muted = false;
            audio.volume = 1;
            audio.playbackRate = 0.92;

            const done = (ok) => {
                if (settled) {
                    return;
                }
                settled = true;
                window.clearTimeout(timer);
                audio.onended = null;
                audio.onerror = null;
                resolve(ok);
            };

            timer = window.setTimeout(() => done(false), 4000);
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

function pickIndonesianVoice() {
    const voices = window.speechSynthesis?.getVoices?.() || [];

    return voices.find((voice) => {
        const lang = (voice.lang || '').toLowerCase().replace('_', '-');

        return lang === 'id-id' || lang.startsWith('id') || lang.startsWith('in');
    });
}

function speakWithBrowserTts(text) {
    return new Promise((resolve) => {
        if (! ('speechSynthesis' in window)) {
            resolve(false);

            return;
        }

        let settled = false;
        let started = false;
        const done = (ok) => {
            if (settled) {
                return;
            }
            settled = true;
            window.clearTimeout(watchdog);
            resolve(ok);
        };

        const watchdog = window.setTimeout(() => done(false), 8000);

        const start = () => {
            if (started || settled) {
                return;
            }
            started = true;

            try {
                window.speechSynthesis.cancel();
                window.speechSynthesis.resume();

                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'id-ID';
                utterance.rate = 0.92;
                utterance.pitch = 1.05;
                utterance.volume = 1;

                const idVoice = pickIndonesianVoice();
                if (idVoice) {
                    utterance.voice = idVoice;
                }

                utterance.onend = () => done(true);
                utterance.onerror = () => done(false);

                window.speechSynthesis.speak(utterance);

                // Chrome kadang menjeda utterance yang baru di-queue.
                window.setTimeout(() => {
                    try {
                        window.speechSynthesis.resume();
                    } catch {
                        //
                    }
                }, 60);
            } catch {
                done(false);
            }
        };

        const voices = window.speechSynthesis.getVoices?.() || [];
        if (voices.length === 0) {
            window.speechSynthesis.addEventListener('voiceschanged', start, { once: true });
            window.setTimeout(start, 250);
        } else {
            start();
        }
    });
}

async function speakNewOrder(text = ORDER_VOICE_TEXT, options = {}) {
    const phrase = (text || ORDER_VOICE_TEXT).trim() || ORDER_VOICE_TEXT;
    const force = Boolean(options.force);
    const now = Date.now();

    if (! force && now - lastSpeakAt < SPEAK_DEDUPE_MS) {
        return;
    }

    if (speakingInFlight && ! force) {
        pendingSpeakText = phrase;

        return;
    }

    speakingInFlight = true;

    try {
        await resumeAudioContext();

        const clipOk = await playVoiceClip();
        if (clipOk) {
            lastSpeakAt = Date.now();
            pendingSpeakText = null;

            return;
        }

        const ttsOk = await speakWithBrowserTts(phrase);
        if (ttsOk) {
            lastSpeakAt = Date.now();
            pendingSpeakText = null;

            return;
        }

        if (playAttentionChime()) {
            lastSpeakAt = Date.now();
            pendingSpeakText = null;

            return;
        }

        pendingSpeakText = phrase;
        showKasirSoundPrompt();
    } finally {
        speakingInFlight = false;
    }
}

function isKasirTabBackground() {
    return document.visibilityState !== 'visible';
}

function kasirNotificationTargetUrl() {
    if (isPinUnlockPage()) {
        return document.body?.dataset?.kasirPinUnlockUrl || '/kasir/pin';
    }

    return document.body?.dataset?.kasirIndexUrl || '/kasir';
}

function showKasirSystemNotification(title, body, extra = {}) {
    if (! ('Notification' in window) || Notification.permission !== 'granted') {
        return Promise.resolve(false);
    }

    const force = Boolean(extra.force);
    if (! force && Date.now() - lastPushWakeAt < 5000) {
        return Promise.resolve(false);
    }

    const options = {
        body,
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        tag: extra.tag || 'kasir-new-order',
        renotify: true,
        silent: false,
        requireInteraction: extra.requireInteraction ?? isKasirTabBackground(),
        vibrate: [200, 100, 200],
        data: { url: kasirNotificationTargetUrl() },
    };

    const openKasir = () => {
        window.focus();
        const target = kasirNotificationTargetUrl();
        if (! window.location.pathname.startsWith('/kasir')) {
            window.location.assign(target);
        }
    };

    if (navigator.serviceWorker?.ready) {
        return navigator.serviceWorker.ready
            .then((registration) => registration.showNotification(title, options))
            .then(() => true)
            .catch(() => {
                try {
                    const notification = new Notification(title, options);
                    notification.onclick = () => {
                        openKasir();
                        notification.close();
                    };

                    return true;
                } catch {
                    return false;
                }
            });
    }

    try {
        const notification = new Notification(title, options);
        notification.onclick = () => {
            openKasir();
            notification.close();
        };

        return Promise.resolve(true);
    } catch {
        return Promise.resolve(false);
    }
}

function showKasirBrowserNotification(title, body) {
    void showKasirSystemNotification(title, body);
}

async function alertNewOrder(toast, options = {}) {
    const title = options.title || 'Pesanan baru masuk';
    const body = options.body || toast || 'Ada pesanan online baru. Buka kasir untuk memproses.';
    const speakText = options.speakText || ORDER_VOICE_TEXT;

    if (toast) {
        showKasirToast(toast);
    }

    await showKasirSystemNotification(title, body);
    await speakNewOrder(speakText);
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

    const customer = String(data.latest_customer || '').trim();
    const preserveKasirDraft = hasActiveKasirDraftWithItems();
    const busy = isKasirTransactionActive();
    const autoLoadWanted = shell.dataset.kasirAutoLoad !== '0';
    const autoLoad = autoLoadWanted && ! preserveKasirDraft && ! busy;
    const orderId = newIds.includes(Number(data.latest_order_id))
        ? Number(data.latest_order_id)
        : Math.max(...newIds);

    // Suara + notifikasi sistem selalu langsung, sama seperti halaman PIN.
    await alertNewOrder(busy || preserveKasirDraft
        ? (customer
            ? `Pesanan baru atas nama ${customer} — cek antrian atas`
            : 'Pesanan online baru masuk — cek antrian atas')
        : (customer
            ? `Pesanan baru atas nama ${customer}`
            : 'Pesanan baru masuk ke kasir'), {
        title: 'Pesanan baru masuk',
        body: customer
            ? `Atas nama ${customer}. Buka kasir untuk memproses.`
            : 'Ada pesanan online baru. Buka kasir untuk memproses.',
        speakText: ORDER_VOICE_TEXT,
    });

    updatePendingPanel(data.html ?? '');
    flashPendingPanel();
    knownOrderIds = currentIds;
    deferredOrderAlert = false;

    if (busy) {
        return;
    }

    isHandlingNewOrder = true;

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

function updatePinOrderAlert(count, customer, isNew) {
    const box = document.querySelector('[data-kasir-order-alert], [data-kasir-pin-alert]');
    if (! box) {
        return;
    }

    const waiting = Math.max(0, Number(count) || 0);
    if (waiting < 1) {
        box.hidden = true;
        box.classList.remove('is-new-alert');

        return;
    }

    box.hidden = false;
    box.classList.toggle('is-new-alert', Boolean(isNew));

    const title = box.querySelector('[data-kasir-order-alert-title], [data-kasir-pin-alert-title]');
    const body = box.querySelector('[data-kasir-order-alert-body], [data-kasir-pin-alert-body]');
    const who = String(customer || '').trim();
    const pinPage = isPinUnlockPage();

    if (title) {
        title.textContent = waiting === 1
            ? 'Pesanan baru menunggu'
            : `${waiting} pesanan menunggu`;
    }

    if (body) {
        body.textContent = who
            ? (pinPage
                ? `Atas nama ${who}. Masukkan PIN untuk membuka kasir.`
                : `Atas nama ${who}. Cek antrian pesanan di atas.`)
            : (pinPage
                ? 'Masukkan PIN untuk membuka kasir.'
                : 'Cek antrian pesanan di atas.');
    }
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
        cache: 'no-store',
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
    const notifyIds = new Set((data.notify_order_ids ?? []).map((id) => Number(id)));
    const notifyCount = Number(data.notify_count ?? notifyIds.size);
    const latestCustomer = data.latest_customer || '';

    if (knownNotifyIds === null) {
        knownNotifyIds = notifyIds;
        knownOrderIds = currentIds;
        updatePinOrderAlert(notifyCount, latestCustomer, false);

        if (! pinPollOnly) {
            updatePendingPanel(data.html ?? '');
        }

        return;
    }

    const newIds = [...notifyIds].filter((id) => ! knownNotifyIds.has(id));
    knownNotifyIds = notifyIds;
    updatePinOrderAlert(notifyCount, latestCustomer, newIds.length > 0);

    if (pinPollOnly) {
        if (newIds.length > 0) {
            await alertNewOrder('Pesanan baru masuk — masukkan PIN untuk membuka kasir', {
                speakText: ORDER_VOICE_TEXT,
            });
        }
        knownOrderIds = currentIds;

        return;
    }

    if (newIds.length > 0) {
        await handleIncomingOrders(newIds, data, shell, currentIds);
    } else if (currentIds.size !== knownOrderIds.size) {
        updatePendingPanel(data.html ?? '');
        knownOrderIds = currentIds;
    } else {
        knownOrderIds = currentIds;
        if (data.html) {
            updatePendingPanel(data.html);
        }
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

function isPinManagementPage() {
    const path = (window.location.pathname || '').replace(/\/+$/, '') || '/';

    return path === '/pin';
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
    const pinPollOnly = isPinPollOnly(shell);
    const minPollSeconds = 4;
    let intervalSeconds = Math.max(
        minPollSeconds,
        parseInt(shell.dataset.kasirPollInterval || '5', 10) || minPollSeconds,
    );
    const continuousPoll = shell.dataset.kasirContinuousPoll !== '0';
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

        pollInFlight = true;
        pollPendingOrders(pollUrl, shell)
            .then(() => {
                intervalSeconds = Math.max(
                    minPollSeconds,
                    parseInt(shell.dataset.kasirPollInterval || String(minPollSeconds), 10) || minPollSeconds,
                );
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
        pollInFlight = true;
        pollPendingOrders(pollUrl, shell)
            .catch(() => {})
            .finally(() => {
                pollInFlight = false;
            });
    };

    window.__kasirPullPending = pullOnce;
    getVoiceAudio();
    showKasirSoundPrompt();
    document.querySelectorAll('[data-kasir-sound-enable]').forEach((button) => {
        button.addEventListener('click', () => {
            void enableKasirOrderAlerts();
        });
    });

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }

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
        void unlockKasirAudio();
        void requestKasirNotifyPermission();
        document.removeEventListener('pointerdown', onFirstKasirGesture, true);
        document.removeEventListener('keydown', onFirstKasirGesture, true);
        document.removeEventListener('touchstart', onFirstKasirGesture, true);
    };

    document.addEventListener('pointerdown', onFirstKasirGesture, { capture: true, passive: true });
    document.addEventListener('keydown', onFirstKasirGesture, { capture: true });
    document.addEventListener('touchstart', onFirstKasirGesture, { capture: true, passive: true });

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event?.data?.type !== 'kasir-wake') {
                return;
            }
            const reason = event.data?.reason || event.data?.data?.type;
            const isOrderWake = reason === 'new_order'
                || event.data?.data?.type === 'new_order'
                || reason === 'notification-click'
                || ! reason;
            if (! isOrderWake) {
                return;
            }
            lastPushWakeAt = Date.now();
            const speakText = event.data?.data?.speak_text || pendingSpeakText || ORDER_VOICE_TEXT;
            void speakNewOrder(speakText, { force: reason === 'notification-click' });
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
            if (pendingSpeakText) {
                const queued = pendingSpeakText;
                pendingSpeakText = null;
                void speakNewOrder(queued, { force: true });
            }
            if (continuousPoll) {
                intervalSeconds = Math.max(
                    minPollSeconds,
                    parseInt(shell.dataset.kasirPollInterval || String(minPollSeconds), 10) || minPollSeconds,
                );
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

document.addEventListener('DOMContentLoaded', () => {
    try {
        initKasirNotifications();
    } catch (error) {
        console.error('kasir notifications failed to start', error);
    }
});
