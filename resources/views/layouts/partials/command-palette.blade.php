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
                @input.debounce.300ms="searchGlobal()"
                @keydown.arrow-down.prevent="navigateCommand('down')"
                @keydown.arrow-up.prevent="navigateCommand('up')"
                @keydown.enter.prevent="executeCommand()"
                type="text"
                placeholder="Search meetings, action items, projects..."
                class="flex-1 bg-transparent text-slate-900 dark:text-slate-100 placeholder-slate-400 text-sm outline-none"
                autocomplete="off"
            >
            <template x-if="searchLoading">
                <svg class="w-4 h-4 text-slate-400 animate-spin flex-shrink-0" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </template>
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

            {{-- Meetings section --}}
            <template x-if="filteredCommands.meetings.length > 0">
                <div class="pt-3 pb-2 px-4 border-t border-slate-100 dark:border-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Meetings</p>
                    <template x-for="(meeting, idx) in filteredCommands.meetings" :key="'meeting-' + (meeting.id || idx)">
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
                            <div class="flex-1 min-w-0">
                                <span x-text="meeting.title" class="truncate block"></span>
                                <span x-show="meeting.mom_number" x-text="meeting.mom_number" class="text-xs text-slate-400"></span>
                            </div>
                            <span x-show="meeting.status" x-text="meeting.status" class="text-xs px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400"></span>
                        </button>
                    </template>
                </div>
            </template>

            {{-- Action Items section --}}
            <template x-if="filteredCommands.action_items && filteredCommands.action_items.length > 0">
                <div class="pt-3 pb-2 px-4 border-t border-slate-100 dark:border-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Action Items</p>
                    <template x-for="(item, idx) in filteredCommands.action_items" :key="'ai-' + item.id">
                        <button
                            @click="executeCommand(filteredCommands.nav.length + filteredCommands.meetings.length + idx)"
                            :class="commandSelectedIndex === (filteredCommands.nav.length + filteredCommands.meetings.length + idx)
                                ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300'
                                : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700'"
                            class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm transition-colors text-left"
                        >
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                            <div class="flex-1 min-w-0">
                                <span x-text="item.title" class="truncate block"></span>
                                <span x-show="item.meeting_title" x-text="item.meeting_title" class="text-xs text-slate-400"></span>
                            </div>
                            <span x-text="item.priority" class="text-xs px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400"></span>
                        </button>
                    </template>
                </div>
            </template>

            {{-- Projects section --}}
            <template x-if="filteredCommands.projects && filteredCommands.projects.length > 0">
                <div class="pt-3 pb-2 px-4 border-t border-slate-100 dark:border-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2">Projects</p>
                    <template x-for="(project, idx) in filteredCommands.projects" :key="'proj-' + project.id">
                        <button
                            @click="executeCommand(filteredCommands.nav.length + filteredCommands.meetings.length + (filteredCommands.action_items?.length || 0) + idx)"
                            :class="commandSelectedIndex === (filteredCommands.nav.length + filteredCommands.meetings.length + (filteredCommands.action_items?.length || 0) + idx)
                                ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300'
                                : 'text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700'"
                            class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm transition-colors text-left"
                        >
                            <svg class="w-4 h-4 text-slate-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                            </svg>
                            <div class="flex-1 min-w-0">
                                <span x-text="project.title" class="truncate block"></span>
                                <span x-show="project.description" x-text="project.description" class="text-xs text-slate-400 truncate block"></span>
                            </div>
                        </button>
                    </template>
                </div>
            </template>

            {{-- Empty state --}}
            <template x-if="commandResultCount === 0 && !searchLoading">
                <div class="py-8 text-center">
                    <p class="text-sm text-slate-400 dark:text-slate-500">
                        No results for "<span x-text="commandQuery"></span>"
                    </p>
                </div>
            </template>
        </div>
    </div>
</div>
