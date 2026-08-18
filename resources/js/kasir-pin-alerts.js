/**
 * Notifikasi halaman PIN — script ringan, tidak bergantung modul POS.
 * Tunai (submitted) dan QRIS lunas (paid) langsung muncul di kartu PIN.
 */
const POLL_MS = 4000;
const ORDER_VOICE_TEXT = 'Pesanan baru masuk';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function pollUrl() {
    return document.body?.dataset?.kasirPollUrl || '';
}

function voiceUrl() {
    return document.body?.dataset?.kasirVoiceUrl || '/sounds/pesanan-masuk.mp3';
}

function updateBanner(count, customer, isNew) {
    const box = document.querySelector('[data-kasir-pin-alert]');
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

    const title = box.querySelector('[data-kasir-pin-alert-title]');
    const body = box.querySelector('[data-kasir-pin-alert-body]');
    const who = String(customer || '').trim();

    if (title) {
        title.textContent = waiting === 1
            ? 'Pesanan baru menunggu'
            : `${waiting} pesanan menunggu`;
    }

    if (body) {
        body.textContent = who
            ? `Atas nama ${who}. Masukkan PIN untuk membuka kasir.`
            : 'Masukkan PIN untuk membuka kasir.';
    }
}

function showOsNotification(title, body) {
    if (! ('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }

    if (Date.now() - lastPushWakeAt < 20000) {
        return;
    }

    const options = {
        body,
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        tag: 'kasir-new-order',
        renotify: true,
        silent: false,
        requireInteraction: document.visibilityState !== 'visible',
        data: { url: '/kasir/pin' },
    };

    const viaSw = navigator.serviceWorker?.ready
        ?.then((reg) => reg.showNotification(title, options))
        .catch(() => false);

    if (viaSw) {
        return;
    }

    try {
        new Notification(title, options);
    } catch {
        //
    }
}

let voiceAudio = null;
let audioContext = null;

function getVoice() {
    if (! voiceAudio) {
        voiceAudio = new Audio(voiceUrl());
        voiceAudio.preload = 'auto';
        voiceAudio.volume = 1;
        voiceAudio.playbackRate = 0.92;
    }

    return voiceAudio;
}

async function unlockAudio() {
    try {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (Ctx && ! audioContext) {
            audioContext = new Ctx();
        }
        if (audioContext?.state === 'suspended') {
            await audioContext.resume();
        }

        const audio = getVoice();
        audio.muted = true;
        try {
            await audio.play();
            audio.pause();
            audio.currentTime = 0;
        } catch {
            //
        }
        audio.muted = false;
    } catch {
        //
    }
}

function playVoice() {
    return new Promise((resolve) => {
        try {
            const audio = getVoice();
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

async function announceNewOrder(customer) {
    const who = String(customer || '').trim();
    const body = who
        ? `Atas nama ${who}. Masukkan PIN untuk membuka kasir.`
        : 'Ada pesanan online baru. Masukkan PIN untuk membuka kasir.';

    showOsNotification('Pesanan baru masuk', body);
    const ok = await playVoice();
    if (! ok && 'speechSynthesis' in window) {
        try {
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(ORDER_VOICE_TEXT);
            utterance.lang = 'id-ID';
            utterance.rate = 0.92;
            window.speechSynthesis.speak(utterance);
        } catch {
            //
        }
    }
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i += 1) {
        output[i] = raw.charCodeAt(i);
    }

    return output;
}

async function subscribeWebPush() {
    const vapidUrl = document.body?.dataset?.kasirPushVapidUrl;
    const subscribeUrl = document.body?.dataset?.kasirPushSubscribeUrl;
    if (! vapidUrl || ! subscribeUrl || ! ('serviceWorker' in navigator) || ! ('PushManager' in window)) {
        return;
    }

    if (! ('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }

    try {
        const vapidRes = await fetch(vapidUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const vapid = await vapidRes.json();
        if (! vapid?.data?.enabled || ! vapid?.data?.public_key) {
            return;
        }

        const registration = await navigator.serviceWorker.register('/sw.js');
        await navigator.serviceWorker.ready;
        const existing = await registration.pushManager.getSubscription();
        const subscription = existing || await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapid.data.public_key),
        });
        const json = subscription.toJSON();
        await fetch(subscribeUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                endpoint: json.endpoint,
                keys: {
                    p256dh: json.keys?.p256dh,
                    auth: json.keys?.auth,
                },
            }),
        });
    } catch {
        //
    }
}

let knownIds = null;
let announcedIds = new Set();
let pollInFlight = false;
let lastPushWakeAt = 0;
let lastAnnounceAt = 0;

async function pollOnce() {
    const url = pollUrl();
    if (! url || pollInFlight) {
        return;
    }

    pollInFlight = true;
    try {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            cache: 'no-store',
        });
        if (! response.ok) {
            return;
        }

        const data = await response.json();
        const ids = (data.notify_order_ids ?? []).map((id) => Number(id)).filter(Boolean);
        const count = Number(data.notify_count ?? ids.length);
        const customer = data.latest_customer || '';

        if (knownIds === null) {
            knownIds = new Set(ids);
            ids.forEach((id) => announcedIds.add(id));
            updateBanner(count, customer, false);

            return;
        }

        const newIds = ids.filter((id) => ! announcedIds.has(id));
        knownIds = new Set(ids);
        ids.forEach((id) => announcedIds.add(id));
        updateBanner(count, customer, newIds.length > 0);
        if (newIds.length > 0 && Date.now() - lastAnnounceAt > 8000) {
            lastAnnounceAt = Date.now();
            await announceNewOrder(customer);
        }
    } catch {
        //
    } finally {
        pollInFlight = false;
    }
}

async function enableAlerts() {
    const buttons = document.querySelectorAll('[data-kasir-sound-enable]');
    buttons.forEach((button) => {
        button.disabled = true;
        button.textContent = 'Mengaktifkan notifikasi…';
    });

    await unlockAudio();
    if ('Notification' in window && Notification.permission === 'default') {
        try {
            await Notification.requestPermission();
        } catch {
            //
        }
    }
    await subscribeWebPush();
    await announceNewOrder('');

    const denied = ('Notification' in window) && Notification.permission === 'denied';
    buttons.forEach((button) => {
        button.disabled = false;
        button.textContent = denied
            ? 'Notifikasi diblokir — izinkan di gembok URL'
            : 'Notifikasi aktif — ketuk untuk tes ulang';
    });
}

function initPinAlerts() {
    if (! document.body?.hasAttribute('data-kasir-notifications')) {
        return;
    }
    if (! document.body.classList.contains('kasir-pin-page')) {
        return;
    }

    getVoice();
    void pollOnce();
    window.setInterval(() => {
        void pollOnce();
    }, POLL_MS);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            void pollOnce();
        }
    });

    document.querySelectorAll('[data-kasir-sound-enable]').forEach((button) => {
        button.addEventListener('click', () => {
            void enableAlerts();
        });
    });

    const unlockOnce = () => {
        void unlockAudio();
        document.removeEventListener('pointerdown', unlockOnce, true);
        document.removeEventListener('keydown', unlockOnce, true);
    };
    document.addEventListener('pointerdown', unlockOnce, { capture: true, passive: true });
    document.addEventListener('keydown', unlockOnce, { capture: true });

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event?.data?.type !== 'kasir-wake') {
                return;
            }

            lastPushWakeAt = Date.now();
            const orderId = Number(event.data?.data?.order_id);
            if (orderId) {
                if (announcedIds.has(orderId)) {
                    return;
                }
                announcedIds.add(orderId);
                lastAnnounceAt = Date.now();
                void announceNewOrder(event.data?.data?.customer_name || '');
            }
            void pollOnce();
        });
    }
}

document.addEventListener('DOMContentLoaded', initPinAlerts);
