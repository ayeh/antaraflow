@php
$mobileItems = [
    [
        'label'  => 'Home',
        'route'  => 'dashboard',
        'active' => request()->routeIs('dashboard'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
    ],
    [
        'label'  => 'Meetings',
        'route'  => 'meetings.index',
        'active' => request()->routeIs('meetings.*'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
    ],
    [
        'label'  => 'Tasks',
        'route'  => 'action-items.dashboard',
        'active' => request()->routeIs('action-items.*'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
    ],
    [
        'label'  => 'AI',
        'route'  => 'meetings.index',
        'active' => false,
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>',
    ],
];
@endphp

{{-- Bottom navigation bar --}}
<nav
    class="fixed bottom-0 left-0 right-0 z-50
           bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700
           px-2 flex items-stretch"
    style="height: calc(64px + env(safe-area-inset-bottom)); padding-bottom: env(safe-area-inset-bottom)"
>
    @foreach($mobileItems as $item)
    <a
        href="{{ route($item['route']) }}"
        class="flex-1 flex flex-col items-center justify-center gap-1 py-2 transition-colors
               {{ $item['active'] ? 'text-primary-600 dark:text-primary-400' : 'text-slate-500 dark:text-slate-400' }}"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            {!! $item['icon'] !!}
        </svg>
        <span class="text-xs font-medium">{{ $item['label'] }}</span>
    </a>
    @endforeach

    {{-- More button --}}
    <button
        @click="bottomSheetOpen = !bottomSheetOpen"
        class="flex-1 flex flex-col items-center justify-center gap-1 py-2 transition-colors
               text-slate-500 dark:text-slate-400"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/>
        </svg>
        <span class="text-xs font-medium">More</span>
    </button>

    {{-- Bottom Sheet --}}
    <div
        x-show="bottomSheetOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex flex-col justify-end"
        style="background: rgba(0,0,0,0.4)"
        @click.self="bottomSheetOpen = false"
    >
        <div
            x-show="bottomSheetOpen"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="bg-white dark:bg-slate-800 rounded-t-3xl p-6"
            style="padding-bottom: max(1.5rem, env(safe-area-inset-bottom))"
        >
            {{-- Drag handle --}}
            <div class="w-12 h-1 rounded-full bg-slate-300 dark:bg-slate-600 mx-auto mb-6"></div>

            <div class="space-y-1">
                <a
                    href="{{ route('organizations.index') }}"
                    class="flex items-center gap-4 px-4 py-3 rounded-xl text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700"
                >
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Settings
                </a>

                <button
                    @click="cycleTheme()"
                    class="w-full flex items-center gap-4 px-4 py-3 rounded-xl text-left
                           text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700"
                >
                    <span class="text-lg" x-text="theme === 'light' ? '☀️' : theme === 'dark' ? '🌙' : '💻'"></span>
                    <span x-text="'Theme: ' + (theme === 'light' ? 'Light' : theme === 'dark' ? 'Dark' : 'System')"></span>
                </button>

                <div class="border-t border-slate-100 dark:border-slate-700 pt-2 mt-2">
                    <div class="px-4 py-2">
                        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="w-full flex items-center gap-4 px-4 py-3 rounded-xl
                                   text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</nav>

{{-- Spacer so content isn't hidden behind bottom nav --}}
<div class="h-16 md:hidden" aria-hidden="true"></div>
