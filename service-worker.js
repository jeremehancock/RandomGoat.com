const CACHE_NAME = "random-goat-v1";
const RUNTIME_CACHE = "random-goat-runtime";

// Assets to cache immediately on install
const PRECACHE_ASSETS = [
  "/",
  "/embed.php",
  "/data/goats.json",
  "/images/goat.png",
  "/images/santa-goat.png",
  "/images/halloween-goat.png",
  "/images/goat-og-img.png",
  "/images/manifest/android-chrome-192x192.png",
  "/images/manifest/android-chrome-512x512.png",
];

// Install event - precache essential assets
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => {
        return cache.addAll(PRECACHE_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

// Activate event - clean up old caches
self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames
            .filter((cacheName) => {
              return cacheName !== CACHE_NAME && cacheName !== RUNTIME_CACHE;
            })
            .map((cacheName) => caches.delete(cacheName))
        );
      })
      .then(() => self.clients.claim())
  );
});

// Fetch event - serve from cache when possible
self.addEventListener("fetch", (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip non-GET requests
  if (request.method !== "GET") {
    return;
  }

  // Skip chrome-extension and other non-http(s) requests
  if (!url.protocol.startsWith("http")) {
    return;
  }

  // Skip analytics and external scripts
  if (
    url.hostname === "www.statcounter.com" ||
    url.hostname === "c.statcounter.com"
  ) {
    return;
  }

  // Handle goat GIF requests
  if (url.pathname.startsWith("/goats/") && url.pathname.endsWith(".gif")) {
    event.respondWith(
      caches.open(RUNTIME_CACHE).then((cache) => {
        return cache.match(request).then((cachedResponse) => {
          // Return cached version if available
          if (cachedResponse) {
            return cachedResponse;
          }

          // Fetch from network and cache
          return fetch(request)
            .then((networkResponse) => {
              // Only cache successful responses
              if (networkResponse && networkResponse.status === 200) {
                cache.put(request, networkResponse.clone());
              }
              return networkResponse;
            })
            .catch(() => {
              // Return a fallback if network fails
              return new Response("Gif not available offline", {
                status: 503,
                statusText: "Service Unavailable",
              });
            });
        });
      })
    );
    return;
  }

  // Handle goats.json - network first, fall back to cache
  if (url.pathname.includes("/data/goats.json")) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Cache the fresh response
          if (response && response.status === 200) {
            const responseClone = response.clone();
            caches.open(CACHE_NAME).then((cache) => {
              cache.put(request, responseClone);
            });
          }
          return response;
        })
        .catch(() => {
          // Fall back to cache if network fails
          return caches.match(request);
        })
    );
    return;
  }

  // For all other requests - cache first, then network
  event.respondWith(
    caches.match(request).then((cachedResponse) => {
      if (cachedResponse) {
        return cachedResponse;
      }

      return fetch(request).then((networkResponse) => {
        // Cache successful responses for future use
        if (networkResponse && networkResponse.status === 200) {
          const responseClone = networkResponse.clone();
          caches.open(RUNTIME_CACHE).then((cache) => {
            cache.put(request, responseClone);
          });
        }
        return networkResponse;
      });
    })
  );
});

// Handle messages from the client
self.addEventListener("message", (event) => {
  if (event.data && event.data.type === "SKIP_WAITING") {
    self.skipWaiting();
  }

  if (event.data && event.data.type === "CLEAR_CACHE") {
    event.waitUntil(
      caches.keys().then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => caches.delete(cacheName))
        );
      })
    );
  }
});
