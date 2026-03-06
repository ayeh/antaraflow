<div
    x-data="onlineStatus"
    x-show="!isOnline || pendingCount > 0"
    x-cloak
    class="fixed top-0 inset-x-0 z-50"
>
    {{-- Offline Banner --}}
    <div
        x-show="!isOnline"
        class="bg-amber-500 text-white text-center text-sm font-medium py-2 px-4"
    >
        <div class="flex items-center justify-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728M5.636 5.636a9 9 0 000 12.728M12 12h.01"/>
                <line x1="4" y1="4" x2="20" y2="20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span>You're offline &mdash; viewing cached data</span>
            <span x-show="pendingCount > 0" class="bg-white/20 rounded-full px-2 py-0.5 text-xs">
                <span x-text="pendingCount"></span> pending
            </span>
        </div>
    </div>

    {{-- Pending Sync Banner (online but has pending actions) --}}
    <div
        x-show="isOnline && pendingCount > 0"
        class="bg-blue-500 text-white text-center text-sm font-medium py-2 px-4"
    >
        <div class="flex items-center justify-center gap-3">
            <span>
                <span x-text="pendingCount"></span> offline
                <span x-text="pendingCount === 1 ? 'action' : 'actions'"></span>
                ready to sync
            </span>
            <button
                @click="syncNow()"
                :disabled="isSyncing"
                class="bg-white/20 hover:bg-white/30 disabled:opacity-50 rounded px-3 py-0.5 text-xs font-semibold transition-colors"
            >
                <span x-show="!isSyncing">Sync Now</span>
                <span x-show="isSyncing" class="flex items-center gap-1">
                    <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Syncing...
                </span>
            </button>
        </div>
    </div>
</div>
