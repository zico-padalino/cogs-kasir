const CACHE_NAME = 'cogs-pos-shell-v1';
const PRECACHE_URLS = [
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/favicon.png',
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

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(cacheFirst(event.request));

        return;
    }

    event.respondWith(networkFirst(event.request));
});

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
