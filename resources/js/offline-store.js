const DB_NAME = 'antaraflow-offline';
const DB_VERSION = 1;
const MEETINGS_STORE = 'meetings';
const ACTIONS_STORE = 'offline_actions';

let dbInstance = null;

/**
 * Open or return the IndexedDB database.
 *
 * @returns {Promise<IDBDatabase>}
 */
export function initDB() {
    if (dbInstance) {
        return Promise.resolve(dbInstance);
    }

    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = event.target.result;

            if (!db.objectStoreNames.contains(MEETINGS_STORE)) {
                const meetingsStore = db.createObjectStore(MEETINGS_STORE, { keyPath: 'id' });
                meetingsStore.createIndex('cached_at', 'cached_at', { unique: false });
            }

            if (!db.objectStoreNames.contains(ACTIONS_STORE)) {
                const actionsStore = db.createObjectStore(ACTIONS_STORE, { keyPath: 'offline_id' });
                actionsStore.createIndex('synced', 'synced', { unique: false });
                actionsStore.createIndex('meeting_id', 'meeting_id', { unique: false });
            }
        };

        request.onsuccess = (event) => {
            dbInstance = event.target.result;
            resolve(dbInstance);
        };

        request.onerror = (event) => {
            reject(event.target.error);
        };
    });
}

/**
 * Store meeting JSON data with a timestamp.
 *
 * @param {number} id
 * @param {Object} data
 * @returns {Promise<void>}
 */
export async function cacheMeeting(id, data) {
    const db = await initDB();
    // Strip sensitive fields before caching
    const { attendees, ...safeData } = data;
    const safeAttendees = (attendees || []).map(({ name, role }) => ({ name, role }));
    return new Promise((resolve, reject) => {
        const tx = db.transaction(MEETINGS_STORE, 'readwrite');
        const store = tx.objectStore(MEETINGS_STORE);
        store.put({ ...safeData, attendees: safeAttendees, id, cached_at: new Date().toISOString(), expires_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString() });
        tx.oncomplete = () => resolve();
        tx.onerror = (event) => reject(event.target.error);
    });
}

/**
 * Retrieve a cached meeting by ID.
 *
 * @param {number} id
 * @returns {Promise<Object|null>}
 */
export async function getCachedMeeting(id) {
    const db = await initDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(MEETINGS_STORE, 'readonly');
        const store = tx.objectStore(MEETINGS_STORE);
        const request = store.get(id);
        request.onsuccess = () => resolve(request.result || null);
        request.onerror = (event) => reject(event.target.error);
    });
}

/**
 * Return all cached meeting IDs and titles.
 *
 * @returns {Promise<Array<{id: number, title: string, cached_at: string}>>}
 */
export async function getCachedMeetingsList() {
    const db = await initDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(MEETINGS_STORE, 'readonly');
        const store = tx.objectStore(MEETINGS_STORE);
        const request = store.getAll();
        request.onsuccess = () => {
            const list = (request.result || []).map((m) => ({
                id: m.id,
                title: m.title,
                cached_at: m.cached_at,
            }));
            resolve(list);
        };
        request.onerror = (event) => reject(event.target.error);
    });
}

/**
 * Queue an offline action for later sync.
 *
 * @param {string} type - 'note' or 'comment'
 * @param {number} meetingId
 * @param {Object} payload
 * @returns {Promise<string>} The offline_id
 */
export async function addOfflineAction(type, meetingId, payload) {
    const db = await initDB();
    const offlineId = `offline_${Date.now()}_${crypto.randomUUID().slice(0, 8)}`;

    return new Promise((resolve, reject) => {
        const tx = db.transaction(ACTIONS_STORE, 'readwrite');
        const store = tx.objectStore(ACTIONS_STORE);
        store.put({
            offline_id: offlineId,
            type,
            meeting_id: meetingId,
            payload,
            synced: 0,
            created_at: new Date().toISOString(),
        });
        tx.oncomplete = () => resolve(offlineId);
        tx.onerror = (event) => reject(event.target.error);
    });
}

/**
 * Get all unsynced offline actions.
 *
 * @returns {Promise<Array>}
 */
export async function getPendingActions() {
    const db = await initDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(ACTIONS_STORE, 'readonly');
        const store = tx.objectStore(ACTIONS_STORE);
        const index = store.index('synced');
        const request = index.getAll(0);
        request.onsuccess = () => resolve(request.result || []);
        request.onerror = (event) => reject(event.target.error);
    });
}

/**
 * Mark a single offline action as synced.
 *
 * @param {string} offlineId
 * @returns {Promise<void>}
 */
export async function markActionSynced(offlineId) {
    const db = await initDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(ACTIONS_STORE, 'readwrite');
        const store = tx.objectStore(ACTIONS_STORE);
        const request = store.get(offlineId);
        request.onsuccess = () => {
            const record = request.result;
            if (record) {
                record.synced = 1;
                store.put(record);
            }
            tx.oncomplete = () => resolve();
        };
        tx.onerror = (event) => reject(event.target.error);
    });
}

/**
 * Evict oldest cached meetings beyond the max count (LRU).
 *
 * @param {number} maxCount
 * @returns {Promise<void>}
 */
export async function evictOldMeetings(maxCount = 50) {
    const db = await initDB();
    return new Promise((resolve, reject) => {
        const tx = db.transaction(MEETINGS_STORE, 'readwrite');
        const store = tx.objectStore(MEETINGS_STORE);
        const index = store.index('cached_at');
        const request = index.getAll();

        request.onsuccess = () => {
            const meetings = request.result || [];
            if (meetings.length <= maxCount) {
                resolve();
                return;
            }

            meetings.sort((a, b) => new Date(a.cached_at) - new Date(b.cached_at));
            const toRemove = meetings.slice(0, meetings.length - maxCount);
            toRemove.forEach((m) => store.delete(m.id));
        };

        tx.oncomplete = () => resolve();
        tx.onerror = (event) => reject(event.target.error);
    });
}

/**
 * Expose a global bridge for inline scripts to trigger offline caching.
 */
window.__offlineStore = {
    async cacheMeetingFromUrl(url) {
        // Security: only allow same-origin meeting data URLs
        const allowed = /^\/meetings\/\d+\/offline-data$/;
        try {
            const parsed = new URL(url, window.location.origin);
            if (parsed.origin !== window.location.origin || !allowed.test(parsed.pathname)) {
                console.warn('Blocked offline cache request to disallowed URL:', url);
                return;
            }
            await initDB();
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (response.ok) {
                const data = await response.json();
                await cacheMeeting(data.id, data);
                await evictOldMeetings(50);
            }
        } catch {
            // Offline caching failed silently
        }
    },
    async clearAll() {
        const db = await initDB();
        await new Promise((resolve) => {
            const tx = db.transaction([MEETINGS_STORE, ACTIONS_STORE], 'readwrite');
            tx.objectStore(MEETINGS_STORE).clear();
            tx.objectStore(ACTIONS_STORE).clear();
            tx.oncomplete = () => resolve();
        });
    },
};
