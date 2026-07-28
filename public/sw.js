const CACHE_NAME = 'cogs-pos-shell-v3';
const PRECACHE_URLS = [
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/favicon.png',
];

/** Halaman sesi/absensi — jangan di-intercept / di-cache SW. */
const BYPASS_PATH_PREFIXES = [
    '/absensi',
    '/attendance',
    '/login',
    '/logout',
    '/hub',
    '/kasir',
    '/admin',
    '/dashboard',
    '/password',
    '/pin',
    '/employee',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS)).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)),
        )).then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Navigasi HTML ke modul/absensi selalu network — hindari "nyangkut" halaman absen.
    if (event.request.mode === 'navigate' || isBypassedPath(url.pathname)) {
        return;
    }

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(cacheFirst(event.request));

        return;
    }

    event.respondWith(networkFirst(event.request));
});

function isBypassedPath(pathname) {
    return BYPASS_PATH_PREFIXES.some((prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`));
}

async function cacheFirst(request) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(CACHE_NAME);
        cache.put(request, response.clone());
    }

    return response;
}

async function networkFirst(request) {
    try {
        return await fetch(request);
    } catch {
        const cached = await caches.match(request);

        if (cached) {
            return cached;
        }

        throw new Error('Offline');
    }
}

self.addEventListener('push', (event) => {
    event.waitUntil((async () => {
        let title = 'Pesanan baru masuk';
        let body = 'Ada pesanan online baru. Buka kasir untuk memproses.';
        let data = { url: '/kasir' };

        try {
            if (event.data) {
                const payload = event.data.json();
                title = payload.title || title;
                body = payload.body || body;
                data = { ...data, ...(payload.data || {}) };
            }
        } catch {
            // Empty wake-up push — show default kasir alert.
        }

        // Tab kasir terbuka → minta pull sekali (tanpa poll berkala).
        try {
            const clientsList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
            for (const client of clientsList) {
                client.postMessage({ type: 'kasir-wake', reason: 'new_order', data });
            }
        } catch {
            // ignore
        }

        await self.registration.showNotification(title, {
            body,
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            tag: 'kasir-new-order',
            renotify: true,
            data,
            vibrate: [200, 100, 200],
        });
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification?.data?.url || '/kasir';

    event.waitUntil((async () => {
        const clientsList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of clientsList) {
            if ('focus' in client) {
                await client.focus();

                if ('navigate' in client) {
                    await client.navigate(targetUrl);
                }

                return;
            }
        }

        if (self.clients.openWindow) {
            await self.clients.openWindow(targetUrl);
        }
    })());
});
