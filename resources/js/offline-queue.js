import { getPendingActions, markActionSynced } from './offline-store';

/**
 * Sync all pending offline actions to the server.
 *
 * @returns {Promise<{synced: number, failed: number}>}
 */
export async function syncPendingActions() {
    const pending = await getPendingActions();

    if (pending.length === 0) {
        return { synced: 0, failed: 0 };
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const actions = pending.map((a) => ({
        type: a.type,
        meeting_id: a.meeting_id,
        payload: a.payload,
        offline_id: a.offline_id,
    }));

    try {
        const response = await fetch('/offline/sync', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: JSON.stringify({ actions }),
        });

        if (!response.ok) {
            return { synced: 0, failed: pending.length };
        }

        const data = await response.json();
        let syncedCount = 0;

        for (const result of data.synced || []) {
            await markActionSynced(result.offline_id);
            syncedCount++;
        }

        return { synced: syncedCount, failed: pending.length - syncedCount };
    } catch {
        return { synced: 0, failed: pending.length };
    }
}

/**
 * Register a background sync tag with the service worker.
 *
 * @returns {Promise<void>}
 */
export async function registerBackgroundSync() {
    if (!('serviceWorker' in navigator) || !('SyncManager' in window)) {
        return;
    }

    try {
        const registration = await navigator.serviceWorker.ready;
        await registration.sync.register('offline-sync');
    } catch {
        // Background sync not supported or permission denied
    }
}
