// Service Worker for Law Firm Website
// Provides caching for better performance

const CACHE_NAME = 'lawfirm-v1';
const urlsToCache = [
    '/',
    '/styles.css',
    '/script.js',
    '/src/img/lawyers.jpg',
    '/src/img/99.jpg',
    '/api/get_lawyers_by_specialization.php',
    '/api/get_lawyer_availability.php'
];

// Install event - cache resources
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                return cache.addAll(urlsToCache);
            })
    );
});

// Fetch event - serve from cache when available
self.addEventListener('fetch', event => {
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // Return cached version or fetch from network
                if (response) {
                    return response;
                }
                return fetch(event.request);
            }
        )
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});
