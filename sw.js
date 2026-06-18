const CACHE_NAME = 'tc-tv-cache-v2';
const urlsToCache = [
  '/iptv/',
  '/iptv/index.php',
  '/iptv/t&c.png',
  '/iptv/manifest.json'
];

// Install event - Cache core assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
  );
  self.skipWaiting();
});

// Fetch event - Cache-busting logic and smart routing
self.addEventListener('fetch', event => {
  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  // 1. Dynamic API: Bypass caching entirely (Network-Only)
  if (url.pathname.includes('api_dynamic.php')) {
    event.respondWith(fetch(event.request));
    return;
  }

  // 2. HTML and Documents: Network-First strategy
  if (event.request.mode === 'navigate' || event.request.headers.get('accept').includes('text/html')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // If valid response, update cache
          if (response && response.status === 200) {
            const responseToCache = response.clone();
            caches.open(CACHE_NAME).then(cache => {
              cache.put(event.request, responseToCache);
            });
          }
          return response;
        })
        .catch(() => {
          // Fallback to cache if offline
          return caches.match(event.request);
        })
    );
    return;
  }

  // 3. Static Assets: Cache-First strategy (with network fallback)
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response;
        }
        
        const fetchRequest = event.request.clone();

        return fetch(fetchRequest).then(
          response => {
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            const responseToCache = response.clone();

            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(event.request, responseToCache);
              });

            return response;
          }
        );
      })
  );
});

// Activate event - Cleanup old caches
self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});
