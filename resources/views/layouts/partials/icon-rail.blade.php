@php
$groups = [
    [
        'key'    => 'home',
        'label'  => 'Home',
        'active' => request()->routeIs('dashboard'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
    ],
    [
        'key'    => 'meetings',
        'label'  => 'Meetings',
        'active' => request()->routeIs('meetings.*')
                    && ! request()->routeIs('meetings.extractions.*', 'meetings.chat.*', 'meetings.transcriptions.*'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
    ],
    [
        'key'    => 'tasks',
        'label'  => 'Tasks',
        'active' => request()->routeIs('action-items.*'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
    ],
    [
        'key'    => 'ai',
        'label'  => 'AI',
        'active' => request()->routeIs('meetings.extractions.*', 'meetings.chat.*', 'meetings.transcriptions.*'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>',
    ],
    [
        'key'    => 'analytics',
        'label'  => 'Analytics',
        'active' => false,
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
    ],
    [
        'key'    => 'settings',
        'label'  => 'Settings',
        'active' => request()->routeIs('organizations.*'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
    ],
];
@endphp

<nav
    class="fixed left-3 top-3 z-50 flex flex-col items-center w-12 rounded-3xl py-3
           bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700
           shadow-[0_4px_6px_-1px_rgba(0,0,0,0.1)]"
    style="height: calc(100vh - 24px)"
>
    {{-- Brand mark --}}
    <a href="{{ route('dashboard') }}" class="flex items-center justify-center mb-4">
        <span class="text-primary-600 dark:text-primary-400 font-black text-xs leading-none tracking-tighter">aF</span>
    </a>

    {{-- Navigation groups --}}
    <div class="flex-1 flex flex-col gap-1 w-full px-1.5">
        @foreach($groups as $group)
        <div class="relative group">
            <button
                @click="activeFlyout = activeFlyout === '{{ $group['key'] }}' ? null : '{{ $group['key'] }}'"
                :class="activeFlyout === '{{ $group['key'] }}' ? 'bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400' : ''"
                class="relative flex items-center justify-center w-full h-10 rounded-xl transition-all duration-150
                       {{ $group['active']
                           ? 'bg-primary-100 dark:bg-primary-900/40 text-primary-600 dark:text-primary-400'
                           : 'text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-slate-700 dark:hover:text-slate-200' }}"
            >
                @if($group['active'])
                <span class="absolute -left-1.5 top-2 bottom-2 w-1 rounded-r-full bg-primary-600 dark:bg-primary-400"></span>
                @endif
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {!! $group['icon'] !!}
                </svg>
            </button>
            {{-- CSS tooltip (500ms delay via Tailwind delay-500) --}}
            <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2
                         rounded-md bg-slate-900 dark:bg-slate-700 text-white text-xs px-2 py-1 whitespace-nowrap
                         opacity-0 group-hover:opacity-100 transition-opacity delay-500 z-50 shadow-lg">
                {{ $group['label'] }}
            </span>
        </div>
        @endforeach
    </div>

    {{-- Avatar / Profile (bottom) --}}
    <div class="relative group mt-auto">
        <button
            @click="activeFlyout = activeFlyout === 'profile' ? null : 'profile'"
            class="flex items-center justify-center w-8 h-8 rounded-full
                   bg-primary-600 dark:bg-primary-500 text-white text-xs font-bold
                   hover:bg-primary-700 dark:hover:bg-primary-600 transition-colors"
        >
            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
        </button>
        <span class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2
                     rounded-md bg-slate-900 dark:bg-slate-700 text-white text-xs px-2 py-1 whitespace-nowrap
                     opacity-0 group-hover:opacity-100 transition-opacity delay-500 z-50 shadow-lg">
            {{ auth()->user()->name }}
        </span>
    </div>
</nav>
