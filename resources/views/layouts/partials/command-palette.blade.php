<div
    x-show="commandPaletteOpen"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-100"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-50 flex items-start justify-center pt-[20vh] px-4"
    style="background: rgba(0,0,0,0.5); backdrop-filter: blur(4px)"
    @click.self="commandPaletteOpen = false"
>
    <div
        x-show="commandPaletteOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="w-full max-w-lg bg-white dark:bg-slate-800 rounded-2xl shadow-2xl
               border border-slate-200 dark:border-slate-700 overflow-hidden"
        style="max-height: 70vh"
        @click.stop
    >
        {{-- Input --}}
        <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-200 dark:border-slate-700">
            <svg class="w-5 h-5 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
                x-ref="commandInput"
                x-model="commandQuery"
                @keydown.arrow-down.prevent="navigateCommand('down')"
                @keydown.arrow-up.prevent="navigateCommand('up')"
                @keydown.enter.prevent="executeCommand()"
                type="text"
                placeholder="Search or type a command…"
                class="flex-1 bg-transparent text-slate-900 dark:text-slate-100 placeholder-slate-400 text-sm outline-none"
                autocomplete="off"
            >
            <kbd class="text-xs text-slate-400 dark:text-slate-500 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-600 font-mono flex-shrink-0">
                Esc
            </kbd>
        </div>

        {{-- Results --}}
        <div class="overflow-y-auto" style="max-height: calc(70vh - 60px)">

            {{-- Navigation section --}}
            <template x-if="filteredCommands.nav.length > 0">
                <div class="pt-3 pb-2 px-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Navigation</p>
                    <template x-for="(item, idx) in filteredCommands.nav" :key="item.label">
                        <button
                            @click="executeCommand(idx)"
                            :class="commandSelectedIndex === idx
                                ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300'
                                : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700'"
                            class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm transition-colors text-left"
                        >
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                            </svg>
                            <span x-text="item.label"></span>
                        </button>
                    </template>
                </div>
            </template>

            {{-- Recent Meetings section --}}
            <template x-if="filteredCommands.meetings.length > 0">
                <div class="pt-3 pb-3 px-4 border-t border-slate-100 dark:border-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Recent Meetings</p>
                    <template x-for="(meeting, idx) in filteredCommands.meetings" :key="meeting.id">
                        <button
                            @click="executeCommand(filteredCommands.nav.length + idx)"
                            :class="commandSelectedIndex === (filteredCommands.nav.length + idx)
                                ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300'
                                : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700'"
                            class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm transition-colors text-left"
                        >
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <span x-text="meeting.title" class="truncate"></span>
                        </button>
                    </template>
                </div>
            </template>

            {{-- Empty state --}}
            <template x-if="filteredCommands.nav.length === 0 && filteredCommands.meetings.length === 0">
                <div class="py-8 text-center">
                    <p class="text-sm text-slate-400 dark:text-slate-500">
                        No results for "<span x-text="commandQuery"></span>"
                    </p>
                </div>
            </template>
        </div>
    </div>
</div>
