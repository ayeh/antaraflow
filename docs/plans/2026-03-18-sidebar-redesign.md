# Sidebar Redesign Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the icon-only rail + flyout panel with a proper collapsible sidebar (icon + text labels, light neutral background, settings flyout retained) to improve navigation usability.

**Architecture:** Single `sidebar.blade.php` replaces both `icon-rail.blade.php` and `flyout-panel.blade.php`. Sidebar state (`sidebarCollapsed`) added to `appState` in `navigation.js` and persisted to `localStorage`. Main content offset in `app.blade.php` reacts to sidebar state via Alpine. Settings sub-items retain a dedicated flyout panel (`settings-flyout.blade.php`).

**Tech Stack:** Laravel 12 Blade, Tailwind CSS v4, Alpine.js (existing `appState`), `localStorage`

---

## Context

**Files to touch:**
- Modify: `resources/js/navigation.js` — add `sidebarCollapsed` + `toggleSidebar()`
- Modify: `resources/views/layouts/app.blade.php` — swap includes, reactive `ml-*`
- Create: `resources/views/layouts/partials/sidebar.blade.php` — full sidebar component
- Create: `resources/views/layouts/partials/settings-flyout.blade.php` — settings sub-items flyout
- Delete: `resources/views/layouts/partials/icon-rail.blade.php`
- Delete: `resources/views/layouts/partials/flyout-panel.blade.php`

**Current architecture:**
- `icon-rail.blade.php` — 48px fixed nav, icon only, opens flyout on click
- `flyout-panel.blade.php` — 240px slide-out panel for ALL nav groups + profile
- Main content offset: `md:ml-[72px]` (hardcoded)
- `activeFlyout` in `appState` drives which flyout group is open

**New architecture:**
- `sidebar.blade.php` — full sidebar, expanded `w-56` / collapsed `w-14`
- `settings-flyout.blade.php` — settings sub-items only, position reacts to sidebar width
- `sidebarCollapsed` in `appState` (boolean, persisted to localStorage)
- Main content offset: `:class="sidebarCollapsed ? 'md:ml-14' : 'md:ml-56'"`

**Design tokens:**
- Sidebar bg: `bg-slate-100 dark:bg-slate-800/60`
- Sidebar border: `border-r border-slate-200 dark:border-slate-700`
- Nav item active: `bg-white dark:bg-slate-700 shadow-sm text-violet-600 dark:text-violet-400`
- Nav item hover: `hover:bg-white dark:hover:bg-slate-700 hover:shadow-sm`
- Nav item normal: `text-slate-600 dark:text-slate-400`
- Expanded width: `w-56` (224px)
- Collapsed width: `w-14` (56px)

---

## Task 1: Add sidebar state to `navigation.js`

**Files:**
- Modify: `resources/js/navigation.js`

**Step 1: Add `sidebarCollapsed` property and `toggleSidebar()` to appState**

Open `resources/js/navigation.js`. The exported object currently starts with:

```js
export default function appState() {
    return {
        activeFlyout: null,
        commandPaletteOpen: false,
        ...
        theme: localStorage.getItem('theme') || 'system',
```

Add after `theme` line (before `bottomSheetOpen`):

```js
        sidebarCollapsed: localStorage.getItem('sidebar_collapsed') === 'true',
```

Add after the `cycleTheme()` method:

```js
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('sidebar_collapsed', this.sidebarCollapsed);
        },
```

**Step 2: Verify the change compiles**

Run: `npm run build 2>&1 | tail -5`
Expected: Build succeeds, no JS errors.

**Step 3: Commit**

```bash
git add resources/js/navigation.js
git commit -m "feat: add sidebarCollapsed state and toggleSidebar to appState"
```

---

## Task 2: Create `settings-flyout.blade.php`

**Files:**
- Create: `resources/views/layouts/partials/settings-flyout.blade.php`

**Step 1: Create the file with settings sub-items only**

```blade
@php
$settingsItems = [
    ['label' => 'Organizations',      'route' => route('organizations.index'),       'active' => request()->routeIs('organizations.index', 'organizations.create')],
    ['label' => 'Meeting Templates',  'route' => route('meeting-templates.index'),   'active' => request()->routeIs('meeting-templates.*')],
    ['label' => 'Meeting Series',     'route' => route('meeting-series.index'),      'active' => request()->routeIs('meeting-series.*')],
    ['label' => 'Tags',               'route' => route('tags.index'),                'active' => request()->routeIs('tags.*')],
    ['label' => 'Attendee Groups',    'route' => route('attendee-groups.index'),     'active' => request()->routeIs('attendee-groups.*')],
    ['label' => 'AI Providers',       'route' => route('ai-provider-configs.index'), 'active' => request()->routeIs('ai-provider-configs.*')],
    ['label' => 'API Keys',           'route' => route('api-keys.index'),            'active' => request()->routeIs('api-keys.*')],
    ['label' => 'Subscription',       'route' => route('subscription.index'),        'active' => request()->routeIs('subscription.*')],
    ['label' => 'Usage',              'route' => route('usage.index'),               'active' => request()->routeIs('usage.*')],
    ['label' => 'Audit Log',          'route' => route('audit-log.index'),           'active' => request()->routeIs('audit-log.*')],
    ['label' => 'Calendar Connections','route' => route('calendar.connections'),     'active' => request()->routeIs('calendar.*')],
];
@endphp

<div
    x-show="activeFlyout === 'settings'"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-x-2"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 -translate-x-2"
    @click.outside="activeFlyout = null"
    :style="'left: ' + (sidebarCollapsed ? '56px' : '224px') + '; top: 12px; max-height: calc(100vh - 24px)'"
    class="fixed z-40 w-60 rounded-2xl
           bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700
           shadow-xl py-3 overflow-y-auto"
>
    <div class="px-4 pb-2">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
            Settings
        </span>
    </div>

    @foreach($settingsItems as $index => $item)
    <a
        href="{{ $item['route'] }}"
        class="flex items-center gap-3 mx-2 px-3 py-2 rounded-xl text-sm transition-all duration-150
               {{ $item['active']
                   ? 'bg-violet-50 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 font-medium'
                   : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' }}"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        style="transition-delay: {{ $index * 30 }}ms"
    >
        {{ $item['label'] }}
    </a>
    @endforeach
</div>
```

**Step 2: Commit**

```bash
git add resources/views/layouts/partials/settings-flyout.blade.php
git commit -m "feat: create settings-flyout partial for sidebar settings sub-items"
```

---

## Task 3: Create `sidebar.blade.php`

**Files:**
- Create: `resources/views/layouts/partials/sidebar.blade.php`

**Step 1: Create the file**

```blade
@php
$navItems = [
    [
        'key'    => 'home',
        'label'  => 'Dashboard',
        'href'   => route('dashboard'),
        'active' => request()->routeIs('dashboard'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
    ],
    [
        'key'    => 'meetings',
        'label'  => 'Meetings',
        'href'   => route('meetings.index'),
        'active' => request()->routeIs('meetings.*')
                    && ! request()->routeIs('meetings.extractions.*', 'meetings.chat.*', 'meetings.transcriptions.*'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
    ],
    [
        'key'    => 'tasks',
        'label'  => 'Action Items',
        'href'   => route('action-items.dashboard'),
        'active' => request()->routeIs('action-items.*'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
    ],
    [
        'key'    => 'projects',
        'label'  => 'Projects',
        'href'   => route('projects.index'),
        'active' => request()->routeIs('projects.*'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>',
    ],
    [
        'key'    => 'ai',
        'label'  => 'AI Tools',
        'href'   => null,
        'active' => request()->routeIs('meetings.extractions.*', 'meetings.chat.*', 'meetings.transcriptions.*'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>',
        'note'   => 'Open a meeting to access AI features',
    ],
    [
        'key'    => 'analytics',
        'label'  => 'Analytics',
        'href'   => route('analytics.index'),
        'active' => request()->routeIs('analytics.*'),
        'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
    ],
];

$isSettingsActive = request()->routeIs(
    'organizations.*', 'meeting-templates.*', 'meeting-series.*',
    'tags.*', 'attendee-groups.*', 'ai-provider-configs.*',
    'api-keys.*', 'subscription.*', 'usage.*', 'audit-log.*', 'calendar.*'
);
@endphp

<nav
    :class="sidebarCollapsed ? 'w-14' : 'w-56'"
    class="fixed left-0 top-0 z-50 flex flex-col h-screen
           bg-slate-100 dark:bg-slate-800/60
           border-r border-slate-200 dark:border-slate-700
           transition-all duration-300 ease-in-out overflow-hidden"
>
    {{-- Brand + Toggle --}}
    <div class="flex items-center justify-between px-3 py-4 shrink-0">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0">
            <span class="text-violet-600 dark:text-violet-400 font-black text-sm leading-none tracking-tighter shrink-0">aF</span>
            <span
                x-show="!sidebarCollapsed"
                x-transition:enter="transition ease-out duration-200 delay-100"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate"
            >antaraFLOW</span>
        </a>
        <button
            @click="toggleSidebar()"
            class="shrink-0 p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200
                   hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
            :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        >
            <svg class="w-4 h-4 transition-transform duration-300" :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
            </svg>
        </button>
    </div>

    {{-- Nav Items --}}
    <div class="flex-1 flex flex-col gap-0.5 px-2 overflow-y-auto">
        @foreach($navItems as $item)
        <div class="relative group">
            @if($item['href'])
            <a
                href="{{ $item['href'] }}"
                class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl transition-all duration-150
                       {{ $item['active']
                           ? 'bg-white dark:bg-slate-700 shadow-sm text-violet-600 dark:text-violet-400 font-medium'
                           : 'text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700 hover:shadow-sm hover:text-slate-800 dark:hover:text-slate-100' }}"
            >
                @if($item['active'])
                <span class="absolute left-0 top-2 bottom-2 w-0.5 rounded-r-full bg-violet-600 dark:bg-violet-400"></span>
                @endif
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {!! $item['icon'] !!}
                </svg>
                <span
                    x-show="!sidebarCollapsed"
                    x-transition:enter="transition ease-out duration-150 delay-75"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="text-sm truncate"
                >{{ $item['label'] }}</span>
            </a>
            @else
            {{-- AI Tools — no link, informational only --}}
            <div
                class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl
                       text-slate-400 dark:text-slate-500 cursor-default"
            >
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    {!! $item['icon'] !!}
                </svg>
                <span
                    x-show="!sidebarCollapsed"
                    x-transition:enter="transition ease-out duration-150 delay-75"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="text-sm truncate"
                >{{ $item['label'] }}</span>
            </div>
            @endif

            {{-- Tooltip (collapsed only) --}}
            <span
                x-show="sidebarCollapsed"
                class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2
                       rounded-md bg-slate-900 dark:bg-slate-700 text-white text-xs px-2 py-1
                       whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity z-50 shadow-lg"
            >{{ $item['label'] }}</span>
        </div>
        @endforeach

        {{-- Divider --}}
        <div class="my-1 border-t border-slate-200 dark:border-slate-700"></div>

        {{-- Settings --}}
        <div class="relative group">
            <button
                @click="activeFlyout = activeFlyout === 'settings' ? null : 'settings'"
                class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl transition-all duration-150
                       {{ $isSettingsActive
                           ? 'bg-white dark:bg-slate-700 shadow-sm text-violet-600 dark:text-violet-400 font-medium'
                           : 'text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700 hover:shadow-sm hover:text-slate-800 dark:hover:text-slate-100' }}"
                :class="activeFlyout === 'settings' ? 'bg-white dark:bg-slate-700 shadow-sm' : ''"
            >
                @if($isSettingsActive)
                <span class="absolute left-0 top-2 bottom-2 w-0.5 rounded-r-full bg-violet-600 dark:bg-violet-400"></span>
                @endif
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span
                    x-show="!sidebarCollapsed"
                    x-transition:enter="transition ease-out duration-150 delay-75"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="text-sm truncate flex-1 text-left"
                >Settings</span>
                <svg
                    x-show="!sidebarCollapsed"
                    class="w-3.5 h-3.5 shrink-0 text-slate-400"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            {{-- Tooltip (collapsed only) --}}
            <span
                x-show="sidebarCollapsed"
                class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2
                       rounded-md bg-slate-900 dark:bg-slate-700 text-white text-xs px-2 py-1
                       whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity z-50 shadow-lg"
            >Settings</span>
        </div>
    </div>

    {{-- Avatar / Profile (bottom) --}}
    <div class="shrink-0 px-2 py-3 border-t border-slate-200 dark:border-slate-700">
        <button
            @click="activeFlyout = activeFlyout === 'profile' ? null : 'profile'"
            class="relative group/profile flex items-center gap-3 w-full px-2 py-2 rounded-xl
                   hover:bg-white dark:hover:bg-slate-700 hover:shadow-sm transition-all duration-150"
            :class="activeFlyout === 'profile' ? 'bg-white dark:bg-slate-700 shadow-sm' : ''"
        >
            <div class="shrink-0 flex items-center justify-center w-8 h-8 rounded-full overflow-hidden
                        bg-violet-600 dark:bg-violet-500 text-white text-xs font-bold">
                @if(auth()->user()->avatar_path)
                    <img src="{{ Storage::url(auth()->user()->avatar_path) }}" alt="{{ auth()->user()->name }}" class="w-full h-full object-cover">
                @else
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                @endif
            </div>
            <div
                x-show="!sidebarCollapsed"
                x-transition:enter="transition ease-out duration-150 delay-75"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="flex-1 min-w-0 text-left"
            >
                <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">{{ auth()->user()->name }}</p>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}</p>
            </div>

            {{-- Tooltip (collapsed only) --}}
            <span
                x-show="sidebarCollapsed"
                class="pointer-events-none absolute left-full ml-3 top-1/2 -translate-y-1/2
                       rounded-md bg-slate-900 dark:bg-slate-700 text-white text-xs px-2 py-1
                       whitespace-nowrap opacity-0 group-hover/profile:opacity-100 transition-opacity z-50 shadow-lg"
            >{{ auth()->user()->name }}</span>
        </button>

        {{-- Profile flyout --}}
        <div
            x-show="activeFlyout === 'profile'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-1"
            @click.outside="activeFlyout = null"
            :style="'left: ' + (sidebarCollapsed ? '56px' : '224px') + '; bottom: 12px'"
            class="fixed z-40 w-60 rounded-2xl
                   bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700
                   shadow-xl py-3"
        >
            <div class="px-4 py-2 mb-1">
                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ auth()->user()->name }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}</p>
                @if(auth()->user()->currentOrganization)
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ auth()->user()->currentOrganization->name }}</p>
                @endif
            </div>
            <div class="border-t border-slate-100 dark:border-slate-700 pt-2 mx-2 space-y-0.5">
                <a
                    href="{{ route('profile.edit') }}"
                    class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm
                           text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                >
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Edit Profile
                </a>
                <button
                    @click="cycleTheme()"
                    class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-left
                           text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                >
                    <span class="w-4 h-4 flex items-center justify-center text-slate-400" x-text="theme === 'light' ? '☀️' : theme === 'dark' ? '🌙' : '💻'"></span>
                    <span x-text="'Theme: ' + (theme === 'light' ? 'Light' : theme === 'dark' ? 'Dark' : 'System')"></span>
                </button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm
                               text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Logout
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
```

**Step 2: Commit**

```bash
git add resources/views/layouts/partials/sidebar.blade.php
git commit -m "feat: create collapsible sidebar with icon+text labels"
```

---

## Task 4: Update `app.blade.php` and delete old files

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Delete: `resources/views/layouts/partials/icon-rail.blade.php`
- Delete: `resources/views/layouts/partials/flyout-panel.blade.php`

**Step 1: Replace icon-rail and flyout-panel includes with sidebar**

In `app.blade.php`, replace:

```blade
        {{-- Icon Rail (desktop only) --}}
        <div class="hidden md:block">
            @include('layouts.partials.icon-rail')
        </div>

        {{-- Flyout Panel (desktop only) --}}
        <div class="hidden md:block">
            @include('layouts.partials.flyout-panel')
        </div>
```

With:

```blade
        {{-- Sidebar (desktop only) --}}
        <div class="hidden md:block">
            @include('layouts.partials.sidebar')
            @include('layouts.partials.settings-flyout')
        </div>
```

**Step 2: Update the main content offset**

Replace:

```blade
        <div class="md:ml-[72px] flex flex-col min-h-screen">
```

With:

```blade
        <div :class="sidebarCollapsed ? 'md:ml-14' : 'md:ml-56'" class="flex flex-col min-h-screen transition-all duration-300 ease-in-out">
```

**Step 3: Delete old files**

```bash
rm resources/views/layouts/partials/icon-rail.blade.php
rm resources/views/layouts/partials/flyout-panel.blade.php
```

**Step 4: Run pint**

```bash
vendor/bin/pint resources/views/layouts/app.blade.php resources/views/layouts/partials/sidebar.blade.php resources/views/layouts/partials/settings-flyout.blade.php --format agent
```

**Step 5: Build assets**

```bash
npm run build 2>&1 | tail -5
```
Expected: Build succeeds.

**Step 6: Run tests to catch any regressions**

```bash
php artisan test --compact
```
Expected: Same failures as before (3 pre-existing LiveMeeting failures). No new failures.

**Step 7: Commit**

```bash
git add resources/views/layouts/app.blade.php
git add resources/views/layouts/partials/settings-flyout.blade.php
git rm resources/views/layouts/partials/icon-rail.blade.php
git rm resources/views/layouts/partials/flyout-panel.blade.php
git commit -m "feat: replace icon-rail+flyout with collapsible sidebar, update layout offset"
```

---

## Task 5: Browser QA

**Step 1: Navigate to dashboard**

Open: `http://antaraflow.test/dashboard`

Check:
- [ ] Sidebar renders with icon + text labels (expanded by default)
- [ ] Background is light neutral (`slate-100`), different from page content
- [ ] Active page (Dashboard) has violet text + white card background + left accent
- [ ] All nav items visible: Dashboard, Meetings, Action Items, Projects, AI Tools, Analytics, Settings

**Step 2: Test collapse/expand**

- [ ] Click `«` toggle button — sidebar collapses to icon-only (`w-14`)
- [ ] Main content area shifts left (smooth transition)
- [ ] Refresh page — sidebar stays collapsed (localStorage persisted)
- [ ] Click `»` toggle button — sidebar expands back
- [ ] Refresh page — sidebar stays expanded

**Step 3: Test tooltips (collapsed state)**

- [ ] Hover each icon — tooltip appears with label text (no delay)
- [ ] Tooltip disappears when mouse leaves

**Step 4: Test Settings flyout**

- [ ] Click Settings icon — flyout panel appears to the right of sidebar
- [ ] All settings sub-items visible and clickable
- [ ] Click outside flyout — flyout closes
- [ ] With sidebar collapsed, flyout position starts at `left: 56px`
- [ ] With sidebar expanded, flyout position starts at `left: 224px`

**Step 5: Test Profile flyout**

- [ ] Click avatar at bottom — profile flyout opens above
- [ ] Shows name, email, organization
- [ ] Edit Profile link works
- [ ] Theme toggle cycles correctly
- [ ] Logout works

**Step 6: Test navigation links**

- [ ] Dashboard → `/dashboard`
- [ ] Meetings → `/meetings`
- [ ] Action Items → `/action-items`
- [ ] Projects → `/projects`
- [ ] Analytics → `/analytics`

**Step 7: Test dark mode**

- [ ] Toggle dark mode via profile flyout
- [ ] Sidebar background, text, active states all render correctly in dark

**Step 8: Commit any fixes**

```bash
git add -p
git commit -m "fix: sidebar QA adjustments"
```
