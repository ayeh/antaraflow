<header class="sticky top-0 z-30 bg-white/80 dark:bg-slate-800/80 backdrop-blur-sm
               border-b border-slate-200 dark:border-slate-700 px-6 py-3
               flex items-center gap-3">
    {{-- Organization name --}}
    <div class="flex-1 min-w-0">
        @if(auth()->user()->currentOrganization)
        <span class="text-sm font-medium text-slate-600 dark:text-slate-400 truncate">
            {{ auth()->user()->currentOrganization->name }}
        </span>
        @endif
    </div>

    {{-- ⌘K search trigger --}}
    <button
        @click="commandPaletteOpen = true"
        class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg
               bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600
               text-slate-500 dark:text-slate-400 text-sm
               hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors"
    >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <span>Search</span>
        <kbd class="ml-1 text-xs px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-600 text-slate-500 dark:text-slate-400 font-mono">⌘K</kbd>
    </button>

    {{-- Theme toggle --}}
    <button
        @click="cycleTheme()"
        :title="theme === 'light' ? 'Switch to dark mode' : theme === 'dark' ? 'Switch to system' : 'Switch to light mode'"
        class="flex items-center justify-center w-8 h-8 rounded-lg
               text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
    >
        <span x-show="theme === 'light'">☀️</span>
        <span x-show="theme === 'dark'">🌙</span>
        <span x-show="theme === 'system'">💻</span>
    </button>

    {{-- Notification bell (placeholder) --}}
    <button
        class="relative flex items-center justify-center w-8 h-8 rounded-lg
               text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
    </button>
</header>
