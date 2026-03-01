<div class="fixed bottom-6 right-6 z-40 flex flex-col items-end gap-3">
    {{-- Mini buttons (shown when fabExpanded) --}}
    <div x-show="fabExpanded" class="flex flex-col items-end gap-2">

        <div
            x-show="fabExpanded"
            x-transition:enter="transition ease-out duration-200 delay-75"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            class="flex items-center gap-3"
        >
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300
                         bg-white dark:bg-slate-800 rounded-xl px-3 py-1.5
                         shadow-md border border-slate-200 dark:border-slate-700">
                New Action Item
            </span>
            <a
                href="{{ route('action-items.dashboard') }}"
                class="flex items-center justify-center w-12 h-12 rounded-full
                       bg-secondary-500 hover:bg-secondary-600 text-white shadow-lg
                       transition-all duration-150 hover:scale-105"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
            </a>
        </div>

        <div
            x-show="fabExpanded"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2 scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150 delay-75"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            class="flex items-center gap-3"
        >
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300
                         bg-white dark:bg-slate-800 rounded-xl px-3 py-1.5
                         shadow-md border border-slate-200 dark:border-slate-700">
                New Meeting
            </span>
            <a
                href="{{ route('meetings.create') }}"
                class="flex items-center justify-center w-12 h-12 rounded-full
                       bg-primary-500 hover:bg-primary-600 text-white shadow-lg
                       transition-all duration-150 hover:scale-105"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </a>
        </div>
    </div>

    {{-- Main FAB --}}
    <button
        @click="fabExpanded = !fabExpanded; activeFlyout = null"
        class="flex items-center justify-center w-14 h-14 rounded-full text-white
               bg-gradient-to-br from-primary-500 to-secondary-500
               shadow-[0_0_20px_rgba(139,92,246,0.4)] hover:shadow-[0_0_30px_rgba(139,92,246,0.6)]
               transition-all duration-200 hover:scale-105"
    >
        <svg
            class="w-6 h-6 transition-transform duration-200"
            :class="fabExpanded ? 'rotate-45' : ''"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
        >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
    </button>
</div>
