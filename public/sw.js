const CACHE_VERSION = 'antaraflow-v2';
const STATIC_CACHE = CACHE_VERSION + '-static';
const PAGE_CACHE = CACHE_VERSION + '-pages';
const OFFLINE_DATA_CACHE = CACHE_VERSION + '-offline-data';

const STATIC_ASSETS = [
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/manifest.json',
];

const OFFLINE_URL = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE).then((cache) => cache.addAll(STATIC_ASSETS))
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(
                keys
                    .filter((key) => key.startsWith('antaraflow-') && !key.startsWith(CACHE_VERSION))
                    .map((key) => caches.delete(key))
            )
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET requests and cross-origin
    if (request.method !== 'GET' || url.origin !== location.origin) {
        return;
    }

    // Network-only for API/CSRF routes
    if (url.pathname.startsWith('/api/') ||
        url.pathname.startsWith('/sanctum/') ||
        url.pathname.startsWith('/livewire/') ||
        url.pathname === '/csrf-token') {
        return;
    }

    // Cache offline-data JSON responses (network-first with cache fallback)
    if (url.pathname.match(/^\/meetings\/\d+\/offline-data$/)) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(OFFLINE_DATA_CACHE).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() => caches.match(request))
        );
        return;
    }

    // Cache-first for Vite build assets
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(STATIC_CACHE).then((cache) => cache.put(request, clone));
                    }
                    return response;
                });
            })
        );
        return;
    }

    // Cache-first for static assets (icons, manifest)
    if (url.pathname.startsWith('/icons/') || url.pathname === '/manifest.json') {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request))
        );
        return;
    }

    // Network-first for HTML pages
    if (request.headers.get('Accept')?.includes('text/html')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(PAGE_CACHE).then((cache) => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() => caches.match(request).then((cached) => cached || caches.match(OFFLINE_URL)))
        );
        return;
    }
});

// Background sync handler for offline actions
self.addEventListener('sync', (event) => {
    if (event.tag === 'offline-sync') {
        event.waitUntil(syncOfflineActions());
    }
});

/**
 * Background sync: read pending actions from IndexedDB, POST to /offline/sync.
 */
async function syncOfflineActions() {
    try {
        const db = await openDB();
        const pending = await getUnsyncedActions(db);

        if (pending.length === 0) {
            return;
        }

        const actions = pending.map((a) => ({
            type: a.type,
            meeting_id: a.meeting_id,
            payload: a.payload,
            offline_id: a.offline_id,
        }));

        const response = await fetch('/offline/sync', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ actions }),
        });

        if (response.ok) {
            const data = await response.json();
            for (const result of data.synced || []) {
                await markSynced(db, result.offline_id);
            }
        }
    } catch {
        // Sync will be retried by the browser
    }
}

function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('antaraflow-offline', 1);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function getUnsyncedActions(db) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('offline_actions', 'readonly');
        const store = tx.objectStore('offline_actions');
        const index = store.index('synced');
        const request = index.getAll(0);
        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => reject(request.error);
    });
}

function markSynced(db, offlineId) {
    return new Promise((resolve, reject) => {
        const tx = db.transaction('offline_actions', 'readwrite');
        const store = tx.objectStore('offline_actions');
        const request = store.get(offlineId);
        request.onsuccess = () => {
            const record = request.result;
            if (record) {
                record.synced = 1;
                store.put(record);
            }
            tx.oncomplete = () => resolve();
        };
        tx.onerror = () => reject(request.error);
    });
}
