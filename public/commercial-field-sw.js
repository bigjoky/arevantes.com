/* Only a public offline notice and brand icons are cached. No CRM data, HTML
 * responses, API results, credentials or pending writes are stored here. */
const CACHE = 'arevantes-field-public-v2';
const PUBLIC_FILES = ['/pwa/offline.html', '/pwa/icon-192.png', '/pwa/icon-512.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(PUBLIC_FILES)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then(async (keys) => {
            await Promise.all(keys.filter((key) => key.startsWith('arevantes-field-public-') && key !== CACHE).map((key) => caches.delete(key)));
            await self.clients.claim();
        }),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    if (PUBLIC_FILES.includes(url.pathname)) {
        event.respondWith(caches.open(CACHE).then(async (cache) => (await cache.match(url.pathname)) || fetch(request)));
    } else if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(async () => (await caches.open(CACHE)).match('/pwa/offline.html')));
    }
});

// Activation is explicit: do not replace a running app during an unsaved form.
self.addEventListener('message', (event) => {
    if (event.data?.type === 'ACTIVATE_UPDATE') self.skipWaiting();
});
