import { getPendingActions } from './offline-store';
import { syncPendingActions, registerBackgroundSync } from './offline-queue';

/**
 * Alpine.js data component for tracking online/offline status
 * and managing the offline action queue.
 */
export default function onlineStatus() {
    return {
        isOnline: navigator.onLine,
        pendingCount: 0,
        isSyncing: false,

        init() {
            this.updatePendingCount();

            window.addEventListener('online', () => {
                this.isOnline = true;
                this.autoSync();
            });

            window.addEventListener('offline', () => {
                this.isOnline = false;
            });

            // Periodically check pending count
            setInterval(() => this.updatePendingCount(), 30000);
        },

        async updatePendingCount() {
            try {
                const pending = await getPendingActions();
                this.pendingCount = pending.length;
            } catch {
                // IndexedDB not available
            }
        },

        async syncNow() {
            if (this.isSyncing || !this.isOnline) {
                return;
            }

            this.isSyncing = true;

            try {
                await syncPendingActions();
                await this.updatePendingCount();
            } finally {
                this.isSyncing = false;
            }
        },

        async autoSync() {
            if (this.pendingCount > 0) {
                await this.syncNow();
                await registerBackgroundSync();
            }
        },
    };
}
