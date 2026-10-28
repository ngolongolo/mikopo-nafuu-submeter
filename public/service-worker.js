const CACHE_NAME = 'mikopo-nafuu-static-v2';
const OFFLINE_URL = '/offline.html';
const STATIC_ASSETS = [
    OFFLINE_URL,
    '/manifest.webmanifest',
    '/app.css',
    '/loading.css',
    '/auth.css',
    '/icons.css',
    '/wizard.css',
    '/onboarding.css',
    '/loan.css',
    '/disbursements.css',
    '/payouts.css',
    '/repayment.css',
    '/upfront.css',
    '/summary-cards.css',
    '/summary-cards.js',
    '/customer-profile.css',
    '/api-docs.css',
    '/customer-leads.css',
    '/customer-portal.css',
    '/pwa.css',
    '/loading.js',
    '/pwa.js',
    '/pwa/icon-192.png',
    '/pwa/icon-512.png',
    '/pwa/icon-maskable-512.png'
];

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(STATIC_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    if (!/\.(?:css|js|png|svg|webmanifest)$/.test(url.pathname)) return;
    event.respondWith(
        caches.match(request).then(cached => {
            const refreshed = fetch(request).then(response => {
                if (response.ok) caches.open(CACHE_NAME).then(cache => cache.put(request, response.clone()));
                return response;
            }).catch(() => cached);
            return cached || refreshed;
        })
    );
});
