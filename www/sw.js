// Service Worker for HeatpumpMonitor.org PWA
const CACHE_NAME = 'heatpumpmonitor-v2';
const OFFLINE_URL = '/';

// Assets to cache on install (third party libraries are vendored under theme/vendor)
const PRECACHE_ASSETS = [
  '/',
  '/theme/style.css',
  '/theme/img/icons/icon-192x192.png',
  '/theme/img/icons/icon-512x512.png',
  '/theme/vendor/bootstrap-5.3.0/css/bootstrap.min.css',
  '/theme/vendor/bootstrap-5.3.0/js/bootstrap.bundle.min.js',
  '/theme/vendor/font-awesome-5.15.4/css/fontawesome.min.css',
  '/theme/vendor/font-awesome-5.15.4/css/solid.min.css'
];

// Install event - cache core assets
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[Service Worker] Precaching assets');
        return cache.addAll(PRECACHE_ASSETS);
      })
      .then(() => self.skipWaiting())
      .catch((error) => {
        console.error('[Service Worker] Precaching failed:', error);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Activating...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('[Service Worker] Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch event - network first, fall back to cache
self.addEventListener('fetch', (event) => {
  // Parse URL to check origin properly
  const url = new URL(event.request.url);
  const allowedOrigins = [
    self.location.origin
  ];
  
  // Skip cross-origin requests that are not from allowed origins
  if (!allowedOrigins.includes(url.origin)) {
    return;
  }

  // Skip non-GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // If valid response, clone and cache it
        // Allow both 'basic' (same-origin) and 'cors' (cross-origin) responses
        if (response && response.status === 200 && (response.type === 'basic' || response.type === 'cors')) {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return response;
      })
      .catch(() => {
        // If network fails, try cache
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          
          // If requesting a page and nothing in cache, return offline page
          if (event.request.mode === 'navigate') {
            return caches.match(OFFLINE_URL);
          }
        });
      })
  );
});

// Handle messages from the client
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});
