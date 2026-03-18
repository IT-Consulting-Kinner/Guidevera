const CACHE_NAME = 'manual-v9';
const STATIC_ASSETS = [
    '/css/app.css',
    '/css/bootstrap.min.css',
    '/css/all.min.css',
    '/js/pages.js',
    '/js/jquery-3.5.1.js',
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Only cache GET requests for same-origin
    if (event.request.method !== 'GET' || url.origin !== location.origin) return;

    // Network-first for API calls and HTML pages
    if (url.pathname.startsWith('/pages/') && !url.pathname.match(/\.(js|css|png|jpg|ico|woff)/)) {
        event.respondWith(
            fetch(event.request).then(response => {
                const clone = response.clone();
                caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                return response;
            }).catch(() => caches.match(event.request))
        );
        return;
    }

    // Cache-first for static assets
    event.respondWith(
        caches.match(event.request).then(cached => cached || fetch(event.request))
    );
});
