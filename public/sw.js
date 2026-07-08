/* AgendaAqui Service Worker — PWA offline + push notifications */
const VERSION = 'v1.0.0';
const STATIC_CACHE = `static-${VERSION}`;
const RUNTIME_CACHE = `runtime-${VERSION}`;
const IMAGE_CACHE = `images-${VERSION}`;
const OFFLINE_URL = '/offline.html';

const PRECACHE_URLS = [
  '/',
  '/offline.html',
  '/manifest.webmanifest',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
];

// ---------- Install ----------
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE_URLS)).then(() => self.skipWaiting())
  );
});

// ---------- Activate — cleanup old caches ----------
self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(
        keys.filter((k) => ![STATIC_CACHE, RUNTIME_CACHE, IMAGE_CACHE].includes(k)).map((k) => caches.delete(k))
      );
      if ('navigationPreload' in self.registration) {
        try { await self.registration.navigationPreload.enable(); } catch {}
      }
      await self.clients.claim();
    })()
  );
});

// ---------- Fetch strategies ----------
self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin && !/\.(png|jpg|jpeg|webp|svg|gif|ico|woff2?)$/i.test(url.pathname)) return;

  // Navigation → network-first + offline fallback
  if (req.mode === 'navigate') {
    event.respondWith(
      (async () => {
        try {
          const preload = await event.preloadResponse;
          if (preload) return preload;
          const fresh = await fetch(req);
          const cache = await caches.open(RUNTIME_CACHE);
          cache.put(req, fresh.clone());
          return fresh;
        } catch {
          const cached = await caches.match(req);
          return cached || caches.match(OFFLINE_URL);
        }
      })()
    );
    return;
  }

  // Images → cache-first
  if (req.destination === 'image') {
    event.respondWith(
      caches.open(IMAGE_CACHE).then(async (cache) => {
        const cached = await cache.match(req);
        if (cached) return cached;
        try {
          const res = await fetch(req);
          if (res.ok) cache.put(req, res.clone());
          return res;
        } catch {
          return cached || new Response('', { status: 504 });
        }
      })
    );
    return;
  }

  // Static assets (JS/CSS/fonts) → stale-while-revalidate
  if (['style', 'script', 'font'].includes(req.destination)) {
    event.respondWith(
      caches.open(RUNTIME_CACHE).then(async (cache) => {
        const cached = await cache.match(req);
        const network = fetch(req).then((res) => {
          if (res && res.ok) cache.put(req, res.clone());
          return res;
        }).catch(() => cached);
        return cached || network;
      })
    );
    return;
  }
});

// ---------- Push notifications ----------
self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; } catch { data = { title: 'AgendaAqui', body: event.data ? event.data.text() : '' }; }
  const title = data.title || 'AgendaAqui';
  const options = {
    body: data.body || '',
    icon: data.icon || '/icons/icon-192.png',
    badge: '/icons/icon-192.png',
    image: data.image,
    tag: data.tag || 'agendaaqui',
    data: { url: data.url || '/' },
    vibrate: [100, 50, 100],
    requireInteraction: !!data.requireInteraction,
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = (event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(
    (async () => {
      const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
      for (const client of clients) {
        if ('focus' in client) {
          await client.focus();
          if ('navigate' in client) await client.navigate(target);
          return;
        }
      }
      await self.clients.openWindow(target);
    })()
  );
});

// ---------- Message from page (skipWaiting) ----------
self.addEventListener('message', (event) => {
  if (event.data === 'SKIP_WAITING') self.skipWaiting();
});
