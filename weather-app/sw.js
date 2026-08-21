/**
 * 🌦️ Weather SLB — Service Worker
 * Phase 5: PWA + Offline support + Push notification handler
 * Cache: Open-Meteo 10min, TMD 30min, Static cache-first
 */
const CACHE_VERSION = 'v3';
const STATIC_CACHE = `weather-static-${CACHE_VERSION}`;
const API_CACHE = `weather-api-${CACHE_VERSION}`;
const RUNTIME_CACHE = `weather-runtime-${CACHE_VERSION}`;

const PRECACHE_URLS = [
  './',
  './index.html',
  './forecast.html',
  './advisory.html',
  './calendar.html',
  './flood-warning.html',
  './manifest.json',
  './assets/logos/logo-ruts.jpg',
  './assets/logos/logo-wj.jpg',
  './assets/logos/logo-doae.jpg'
];

const API_HOSTS = [
  'open-meteo.com',
  'marine-api.open-meteo.com',
  'api.tmd.go.th',
  'data.tmd.go.th',
  'tile.openstreetmap.org',
  'server.arcgisonline.com'
];

// ===== Install: Pre-cache static assets =====
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => {
      return cache.addAll(PRECACHE_URLS).catch((err) => {
        console.log('[SW] Pre-cache partial failure (non-critical):', err.message);
      });
    })
  );
  self.skipWaiting();
});

// ===== Activate: Clean old caches =====
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames
          .filter((name) => {
            // Delete all caches except current version
            return name !== STATIC_CACHE && name !== API_CACHE && name !== RUNTIME_CACHE;
          })
          .map((name) => {
            console.log('[SW] Deleting old cache:', name);
            return caches.delete(name);
          })
      );
    })
  );
  self.clients.claim();
});

// ===== Fetch: Strategy depends on request type =====
self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);

  // Skip non-GET and non-HTTP(S) requests
  if (event.request.method !== 'GET') return;
  if (!url.protocol.startsWith('http')) return;

  // HTML documents (network-first - always get latest, no cache bug)
  if (event.request.destination === 'document') {
    event.respondWith(networkFirst(event.request));
    return;
  }

  // API requests (stale-while-revalidate)
  if (API_HOSTS.some((host) => url.hostname.includes(host))) {
    event.respondWith(staleWhileRevalidate(event.request, API_CACHE));
    return;
  }

  // Same-origin static assets (cache-first)
  if (url.origin === location.origin) {
    event.respondWith(cacheFirst(event.request));
    return;
  }
});

// Stale-while-revalidate strategy
async function staleWhileRevalidate(request, cacheName) {
  const cache = await caches.open(cacheName);
  const cached = await cache.match(request);
  const networkPromise = fetch(request)
    .then((response) => {
      if (response.ok) {
        cache.put(request, response.clone());
      }
      return response;
    })
    .catch(() => cached);
  return cached || networkPromise;
}

// Network-first for HTML (always get latest version)
async function networkFirst(request) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(RUNTIME_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    const cached = await caches.match(request);
    if (cached) return cached;
    throw err;
  }
}

// Cache-first strategy
async function cacheFirst(request) {
  const cached = await caches.match(request);
  if (cached) return cached;
  try {
    const response = await fetch(request);
    if (response.ok && response.type === 'basic') {
      const cache = await caches.open(RUNTIME_CACHE);
      cache.put(request, response.clone());
    }
    return response;
  } catch (err) {
    // Offline fallback for HTML
    if (request.destination === 'document') {
      return caches.match('./index.html');
    }
  }
}

// ===== Messages from page =====
self.addEventListener('message', (event) => {
  if (!event.data) return;
  if (event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  if (event.data.type === 'CLEAR_CACHE') {
    caches.keys().then((keys) => Promise.all(keys.map((k) => caches.delete(k))))
      .then(() => event.ports[0]?.postMessage({ success: true }));
  }
});

// ===== Push Notifications (for future TMD warning push) =====
self.addEventListener('push', (event) => {
  if (!event.data) return;
  let data = {};
  try {
    data = event.data.json();
  } catch (e) {
    data = { title: '🌦️ Weather Alert', body: event.data.text() };
  }
  const title = data.title || '🌦️ Weather Alert';
  const options = {
    body: data.body || 'มีการแจ้งเตือนสภาพอากาศ',
    icon: 'assets/logos/logo-ruts.jpg',
    badge: 'assets/logos/logo-ruts.jpg',
    tag: data.tag || 'weather-alert',
    requireInteraction: data.requireInteraction !== false,
    data: { url: data.url || '/', zone: data.zone || null },
    actions: [
      { action: 'open', title: 'เปิดดู' },
      { action: 'close', title: 'ปิด' }
    ]
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = event.notification.data?.url || '/';
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then((windowClients) => {
        for (const client of windowClients) {
          if ('focus' in client) return client.focus();
        }
        if (clients.openWindow) return clients.openWindow(url);
      })
  );
});

console.log('[SW] Service Worker loaded — cache version:', CACHE_VERSION);
