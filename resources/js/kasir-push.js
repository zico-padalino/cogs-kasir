/**
 * Web Push untuk kasir — notifikasi tetap muncul saat tab/browser tertutup.
 */
function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
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

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
        credentials: 'same-origin',
        ...options,
    });

    const json = await response.json().catch(() => ({}));

    if (! response.ok) {
        throw new Error(json.message || `HTTP ${response.status}`);
    }

    return json;
}

async function ensureServiceWorker() {
    if (! ('serviceWorker' in navigator)) {
        return null;
    }

    const registration = await navigator.serviceWorker.register('/sw.js');
    await navigator.serviceWorker.ready;

    return registration;
}

async function subscribePush(registration, publicKey) {
    const existing = await registration.pushManager.getSubscription();

    if (existing) {
        return existing;
    }

    return registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey),
    });
}

async function initKasirPush() {
    const root = document.body;

    if (! root?.hasAttribute('data-kasir-notifications')) {
        return { ok: false, reason: 'not-kasir' };
    }

    if (! ('Notification' in window) || ! ('PushManager' in window) || ! ('serviceWorker' in navigator)) {
        return { ok: false, reason: 'unsupported' };
    }

    const vapidUrl = root.dataset.kasirPushVapidUrl;
    const subscribeUrl = root.dataset.kasirPushSubscribeUrl;

    if (! vapidUrl || ! subscribeUrl) {
        return { ok: false, reason: 'missing-url' };
    }

    try {
        const vapid = await fetchJson(vapidUrl);

        if (! vapid?.data?.enabled || ! vapid?.data?.public_key) {
            return { ok: false, reason: 'push-disabled' };
        }

        let permission = Notification.permission;

        if (permission === 'default') {
            permission = await Notification.requestPermission();
        }

        if (permission !== 'granted') {
            return { ok: false, reason: 'denied', permission };
        }

        const registration = await ensureServiceWorker();

        if (! registration) {
            return { ok: false, reason: 'no-sw' };
        }

        const subscription = await subscribePush(registration, vapid.data.public_key);
        const json = subscription.toJSON();

        await fetchJson(subscribeUrl, {
            method: 'POST',
            body: JSON.stringify({
                endpoint: json.endpoint,
                keys: {
                    p256dh: json.keys?.p256dh,
                    auth: json.keys?.auth,
                },
            }),
        });

        return { ok: true, permission, endpoint: json.endpoint };
    } catch {
        return { ok: false, reason: 'error' };
    }
}

window.__kasirInitPush = initKasirPush;

document.addEventListener('DOMContentLoaded', () => {
    window.setTimeout(() => {
        void initKasirPush();
    }, 1500);

    // Izin notifikasi butuh gestur; halaman PIN sering baru diketuk belakangan.
    const retryPushOnGesture = () => {
        void initKasirPush();
        document.removeEventListener('pointerdown', retryPushOnGesture, true);
        document.removeEventListener('keydown', retryPushOnGesture, true);
    };
    document.addEventListener('pointerdown', retryPushOnGesture, { capture: true, passive: true });
    document.addEventListener('keydown', retryPushOnGesture, { capture: true });

    // Listener global: push wake → pull sekalipun subscribe VAPID belum selesai.
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event?.data?.type !== 'kasir-wake') {
                return;
            }
            if (typeof window.__kasirPullPending === 'function') {
                window.__kasirPullPending();
            } else {
                window.dispatchEvent(new CustomEvent('kasir:pull-pending'));
            }
        });
    }
});
