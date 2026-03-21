<header class="sticky top-3 z-30 bg-white/80 dark:bg-slate-800/80 backdrop-blur-sm
               border-b border-slate-200 dark:border-slate-700 px-6 h-16
               flex items-center gap-3">
    {{-- Organization name + logo --}}
    <div class="flex-1 min-w-0 flex items-center gap-2">
        @if(auth()->user()->currentOrganization)
            @if(auth()->user()->currentOrganization->logo_path)
                <img src="{{ Storage::url(auth()->user()->currentOrganization->logo_path) }}" alt="{{ auth()->user()->currentOrganization->name }}" class="w-6 h-6 rounded object-cover flex-shrink-0">
            @endif
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

    {{-- Notification Bell --}}
    <div x-data="{ open: false, count: 0, items: [] }"
         x-init="fetch('{{ route('notifications.unread') }}').then(r=>r.json()).then(d=>{ count=d.count; items=d.notifications.slice(0,5); })"
         class="relative">
        <button @click="open=!open" class="relative flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span x-show="count > 0" x-text="count"
                  class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center leading-none"></span>
        </button>
        <div x-show="open" x-transition @click.outside="open=false"
             class="absolute right-0 mt-2 w-80 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-lg z-50">
            <div class="p-3 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
                <span class="text-sm font-semibold text-gray-800 dark:text-white">Notifications</span>
                <a href="{{ route('notifications.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">View all</a>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <template x-for="n in items" :key="n.id">
                    <div class="px-4 py-3 border-b border-gray-50 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/30 last:border-0 transition-colors" :class="{ 'bg-indigo-50 dark:bg-indigo-900/10': !n.read_at }">
                        <p class="text-sm text-gray-700 dark:text-gray-300" x-text="n.data?.message ?? 'Notification'"></p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1" x-text="new Date(n.created_at).toLocaleDateString()"></p>
                    </div>
                </template>
                <div x-show="items.length === 0" class="px-4 py-6 text-center text-sm text-gray-400 dark:text-gray-500">No new notifications</div>
            </div>
            <div class="p-3 border-t border-gray-100 dark:border-slate-700 text-center">
                <a href="{{ route('notifications.index') }}" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">View all notifications</a>
            </div>
        </div>
    </div>
</header>
