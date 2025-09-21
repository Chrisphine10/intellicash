// Service Worker for IntelliCash PWA
const CACHE_NAME = 'intellicash-pwa-v1';
const OFFLINE_URL = '/offline';

// Assets to cache on install
const STATIC_CACHE_URLS = [
    '/',
    '/public/backend/assets/css/styles.css',
    '/public/backend/assets/css/responsive.css',
    '/public/backend/assets/js/scripts.js',
    '/public/backend/assets/js/vendor/jquery-3.7.1.min.js',
    '/public/backend/plugins/bootstrap/css/bootstrap.min.css',
    '/public/backend/plugins/bootstrap/js/bootstrap.min.js',
    '/public/uploads/media/pwa-icon-192x192.png',
    '/public/uploads/media/pwa-icon-512x512.png',
    '/offline'
];

// API endpoints that should be cached
const API_CACHE_PATTERNS = [
    /\/api\/dashboard/,
    /\/api\/transactions/,
    /\/api\/profile/
];

// Install event - cache static assets
self.addEventListener('install', event => {
    console.log('Service Worker: Installing...');
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Service Worker: Caching static assets');
                return cache.addAll(STATIC_CACHE_URLS);
            })
            .then(() => {
                console.log('Service Worker: Installation complete');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Service Worker: Installation failed', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker: Activating...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== CACHE_NAME) {
                            console.log('Service Worker: Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker: Activation complete');
                return self.clients.claim();
            })
    );
});

// Fetch event - implement caching strategies
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip cross-origin requests
    if (url.origin !== location.origin) {
        return;
    }

    // Handle different types of requests
    if (isAPIRequest(request)) {
        event.respondWith(handleAPIRequest(request));
    } else if (isStaticAsset(request)) {
        event.respondWith(handleStaticAsset(request));
    } else {
        event.respondWith(handlePageRequest(request));
    }
});

// Check if request is for API
function isAPIRequest(request) {
    const url = new URL(request.url);
    return url.pathname.startsWith('/api/') || 
           url.pathname.startsWith('/dashboard') ||
           url.pathname.startsWith('/transactions') ||
           url.pathname.startsWith('/profile');
}

// Check if request is for static asset
function isStaticAsset(request) {
    const url = new URL(request.url);
    return url.pathname.includes('/css/') ||
           url.pathname.includes('/js/') ||
           url.pathname.includes('/images/') ||
           url.pathname.includes('/uploads/') ||
           url.pathname.includes('/plugins/');
}

// Handle API requests with network-first strategy
async function handleAPIRequest(request) {
    try {
        // Try network first
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful responses
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Service Worker: Network failed for API request, trying cache');
        
        // Fallback to cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline response for API calls
        return new Response(
            JSON.stringify({ 
                error: 'Offline', 
                message: 'You are currently offline. Please check your connection.' 
            }),
            { 
                status: 503, 
                headers: { 'Content-Type': 'application/json' } 
            }
        );
    }
}

// Handle static assets with cache-first strategy
async function handleStaticAsset(request) {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
        return cachedResponse;
    }
    
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Service Worker: Failed to fetch static asset:', request.url);
        return new Response('Asset not available offline', { status: 404 });
    }
}

// Handle page requests with network-first strategy
async function handlePageRequest(request) {
    try {
        const networkResponse = await fetch(request);
        return networkResponse;
    } catch (error) {
        console.log('Service Worker: Network failed for page request, trying cache');
        
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Show offline page for navigation requests
        if (request.mode === 'navigate') {
            return caches.match(OFFLINE_URL);
        }
        
        return new Response('Page not available offline', { status: 404 });
    }
}

// Handle background sync (if supported)
self.addEventListener('sync', event => {
    console.log('Service Worker: Background sync triggered');
    
    if (event.tag === 'background-sync') {
        event.waitUntil(doBackgroundSync());
    }
});

// Background sync implementation
async function doBackgroundSync() {
    try {
        // Sync any pending data when connection is restored
        console.log('Service Worker: Performing background sync');
        
        // You can implement specific sync logic here
        // For example, sync pending transactions, upload offline data, etc.
        
    } catch (error) {
        console.error('Service Worker: Background sync failed', error);
    }
}

// Handle push notifications (if implemented)
self.addEventListener('push', event => {
    console.log('Service Worker: Push notification received');
    
    const options = {
        body: event.data ? event.data.text() : 'New notification from IntelliCash',
        icon: '/public/uploads/media/pwa-icon-192x192.png',
        badge: '/public/uploads/media/pwa-icon-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'View',
                icon: '/public/uploads/media/pwa-icon-72x72.png'
            },
            {
                action: 'close',
                title: 'Close',
                icon: '/public/uploads/media/pwa-icon-72x72.png'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification('IntelliCash', options)
    );
});

// Handle notification click
self.addEventListener('notificationclick', event => {
    console.log('Service Worker: Notification clicked');
    
    event.notification.close();
    
    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/dashboard')
        );
    }
});
