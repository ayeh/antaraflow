# UI Navigation Redesign Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace the traditional 264px sidebar with a Hybrid Navigation system: 48px Icon Rail, Flyout Panel, ⌘K Command Palette, FAB, Mobile Bottom Nav, and Dark Mode.

**Architecture:** Single `x-data="appState"` Alpine.js component on the body wrapper owns all navigation state. Six new Blade partials are included into a refactored `app.blade.php`. The 264px sidebar is deleted. A ViewComposer injects `$recentMeetings` for the Command Palette.

**Tech Stack:** Alpine.js v3 (existing), Tailwind CSS v4 `@theme`, Laravel Blade partials, PHP 8.4 / Laravel 12.

---

## Task 1: Extend Color System

**Files:**
- Modify: `resources/css/app.css`

**Step 1: Replace the file contents**

```css
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';

    /* Primary — Violet */
    --color-primary-50:  #F5F3FF;
    --color-primary-100: #EDE9FE;
    --color-primary-500: #8B5CF6;
    --color-primary-600: #7C3AED;
    --color-primary-700: #6D28D9;

    /* Secondary — Blue */
    --color-secondary-500: #3B82F6;
    --color-secondary-600: #2563EB;

    /* Accent — Teal */
    --color-accent-500: #2DD4BF;
    --color-accent-600: #0D9488;
}
```

**Step 2: Verify build**

```bash
npm run build
```

Expected: exits 0, `public/build/manifest.json` updated.

**Step 3: Commit**

```bash
git add resources/css/app.css
git commit -m "feat: add primary/secondary/accent color tokens to Tailwind @theme"
```

---

## Task 2: Alpine Navigation Module

**Files:**
- Create: `resources/js/navigation.js`
- Modify: `resources/js/app.js`

**Step 1: Create `resources/js/navigation.js`**

```javascript
export default function appState() {
    return {
        activeFlyout: null,
        commandPaletteOpen: false,
        commandQuery: '',
        commandSelectedIndex: 0,
        fabExpanded: false,
        theme: localStorage.getItem('theme') || 'system',
        bottomSheetOpen: false,
        recentMeetings: [],
        navCommands: [
            { label: 'Dashboard',    href: '/dashboard' },
            { label: 'Meetings',     href: '/meetings' },
            { label: 'New Meeting',  href: '/meetings/create' },
            { label: 'Action Items', href: '/action-items' },
            { label: 'Settings',     href: '/organizations' },
        ],

        init() {
            this.applyTheme(this.theme);
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    this.commandPaletteOpen = true;
                    this.$nextTick(() => this.$refs.commandInput?.focus());
                }
                if (e.key === 'Escape') {
                    this.commandPaletteOpen = false;
                    this.activeFlyout    = null;
                    this.fabExpanded     = false;
                    this.bottomSheetOpen = false;
                }
            });
        },

        applyTheme(theme) {
            if (theme === 'system') {
                const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.classList.toggle('dark', isDark);
            } else {
                document.documentElement.classList.toggle('dark', theme === 'dark');
            }
            this.theme = theme;
            localStorage.setItem('theme', theme);
        },

        cycleTheme() {
            const themes = ['light', 'dark', 'system'];
            const next = themes[(themes.indexOf(this.theme) + 1) % 3];
            this.applyTheme(next);
        },

        get filteredCommands() {
            const q = this.commandQuery.toLowerCase();
            return {
                nav: q
                    ? this.navCommands.filter(c => c.label.toLowerCase().includes(q))
                    : this.navCommands,
                meetings: q
                    ? this.recentMeetings.filter(m => m.title.toLowerCase().includes(q))
                    : this.recentMeetings.slice(0, 5),
            };
        },

        get commandResultCount() {
            return this.filteredCommands.nav.length + this.filteredCommands.meetings.length;
        },

        navigateCommand(direction) {
            if (direction === 'up' && this.commandSelectedIndex > 0) {
                this.commandSelectedIndex--;
            } else if (direction === 'down' && this.commandSelectedIndex < this.commandResultCount - 1) {
                this.commandSelectedIndex++;
            }
        },

        executeCommand(index) {
            const meetingLinks = this.filteredCommands.meetings.map(m => ({
                href: `/meetings/${m.id}`,
            }));
            const all = [...this.filteredCommands.nav, ...meetingLinks];
            const item = all[index ?? this.commandSelectedIndex];
            if (!item) { return; }
            this.commandPaletteOpen = false;
            window.location.href = item.href;
        },
    };
}
```

**Step 2: Update `resources/js/app.js`**

```javascript
import './bootstrap';
import Alpine from 'alpinejs';
import appState from './navigation';

window.Alpine = Alpine;
Alpine.data('appState', appState);
Alpine.start();
```

**Step 3: Build**

```bash
npm run build
```

Expected: exits 0, no JS errors.

**Step 4: Commit**

```bash
git add resources/js/navigation.js resources/js/app.js
git commit -m "feat: add Alpine appState navigation module"
```

---

## Task 3: Refactor App Shell

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

**Step 1: Replace the file with the new app shell**

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'antaraFLOW' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 transition-colors">
    <div
        x-data="appState"
        x-init="recentMeetings = @json($recentMeetings ?? [])"
        class="min-h-full"
    >
        {{-- Icon Rail (desktop only) --}}
        <div class="hidden md:block">
            @include('layouts.partials.icon-rail')
        </div>

        {{-- Flyout Panel (desktop only) --}}
        <div class="hidden md:block">
            @include('layouts.partials.flyout-panel')
        </div>

        {{-- Main content area: offset by rail width (48px) + left margin (12px) + gap (12px) = 72px --}}
        <div class="md:ml-[72px] flex flex-col min-h-screen">
            @include('layouts.partials.header')

            @if(session('success'))
                <div class="mx-6 mt-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-300 px-4 py-3 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mx-6 mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 px-4 py-3 rounded-lg text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <main class="flex-1 p-6">
                @yield('content')
            </main>
        </div>

        {{-- Command Palette (all screen sizes) --}}
        @include('layouts.partials.command-palette')

        {{-- FAB (all screen sizes) --}}
        @include('layouts.partials.fab')

        {{-- Mobile Bottom Nav --}}
        <div class="md:hidden">
            @include('layouts.partials.mobile-bottom-nav')
        </div>
    </div>
</body>
</html>
```

**Step 2: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "feat: refactor app shell for hybrid navigation layout"
```

---

## Task 4: Icon Rail Partial

**Files:**
- Create: `resources/views/layouts/partials/icon-rail.blade.php`

The rail is `fixed left-3 top-3`, 48px wide, pill-shaped (`rounded-3xl`), `height: calc(100vh - 24px)`.
Active state: left violet bar + background tint.
Hover state: CSS tooltip via `group` / `group-hover`.

AI routes are meeting sub-resources (`meetings.extractions.*`, `meetings.chat.*`, `meetings.transcriptions.*`), so the Meetings active check explicitly excludes them to avoid double-highlighting.

**Step 1: Create the file**

```blade
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
```

**Step 2: Commit**

```bash
git add resources/views/layouts/partials/icon-rail.blade.php
git commit -m "feat: add icon rail partial"
```

---

## Task 5: Flyout Panel Partial

**Files:**
- Create: `resources/views/layouts/partials/flyout-panel.blade.php`

Position: `fixed left-[68px]` (rail right edge 60px + 8px gap). Width: 240px. Rounded: `rounded-2xl`.
Animation: slide + fade on `activeFlyout` change. Items animate in with staggered translate-y.

**Step 1: Create the file**

```blade
@php
$flyoutGroups = [
    'home' => [
        'title' => 'Home',
        'items' => [
            ['label' => 'Dashboard', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
        ],
    ],
    'meetings' => [
        'title' => 'Meetings',
        'items' => [
            ['label' => 'All Meetings', 'route' => route('meetings.index'),  'active' => request()->routeIs('meetings.index')],
            ['label' => 'New Meeting',  'route' => route('meetings.create'), 'active' => request()->routeIs('meetings.create')],
        ],
    ],
    'tasks' => [
        'title' => 'Tasks',
        'items' => [
            ['label' => 'Action Items', 'route' => route('action-items.dashboard'), 'active' => request()->routeIs('action-items.dashboard')],
        ],
    ],
    'ai' => [
        'title' => 'AI Tools',
        'items' => [],
        'note'  => 'Open a meeting and use the Transcriptions, Extractions, or Chat tabs to access AI features.',
    ],
    'analytics' => [
        'title' => 'Analytics',
        'items' => [],
        'note'  => 'Coming in Phase 2.',
    ],
    'settings' => [
        'title' => 'Settings',
        'items' => [
            ['label' => 'Organizations', 'route' => route('organizations.index'), 'active' => request()->routeIs('organizations.index', 'organizations.create')],
        ],
    ],
    'profile' => [
        'title'   => 'Account',
        'items'   => [],
        'profile' => true,
    ],
];
@endphp

<div
    x-show="activeFlyout !== null"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 -translate-x-2"
    x-transition:enter-end="opacity-100 translate-x-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-x-0"
    x-transition:leave-end="opacity-0 -translate-x-2"
    @click.outside="activeFlyout = null"
    class="fixed left-[68px] z-40 w-60 rounded-2xl
           bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700
           shadow-xl py-3 overflow-y-auto"
    style="top: 12px; max-height: calc(100vh - 24px)"
>
    @foreach($flyoutGroups as $key => $group)
    <div x-show="activeFlyout === '{{ $key }}'">
        {{-- Group title --}}
        <div class="px-4 pb-2">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                {{ $group['title'] }}
            </span>
        </div>

        {{-- Nav items --}}
        @foreach($group['items'] as $index => $item)
        <a
            href="{{ $item['route'] }}"
            class="flex items-center gap-3 mx-2 px-3 py-2 rounded-xl text-sm transition-all duration-150
                   {{ $item['active']
                       ? 'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 font-medium'
                       : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700' }}"
            x-show="activeFlyout === '{{ $key }}'"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            style="transition-delay: {{ $index * 50 }}ms"
        >
            {{ $item['label'] }}
        </a>
        @endforeach

        {{-- Note (for groups with no direct items) --}}
        @if(!empty($group['note']))
        <p class="mx-4 mt-2 text-xs text-slate-400 dark:text-slate-500 leading-relaxed">
            {{ $group['note'] }}
        </p>
        @endif

        {{-- Profile group --}}
        @if(!empty($group['profile']))
        <div class="px-4 py-2 mb-1">
            <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ auth()->user()->name }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400 truncate">{{ auth()->user()->email }}</p>
            @if(auth()->user()->currentOrganization)
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">{{ auth()->user()->currentOrganization->name }}</p>
            @endif
        </div>
        <div class="border-t border-slate-100 dark:border-slate-700 pt-2 mx-2">
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
        @endif
    </div>
    @endforeach
</div>
```

**Step 2: Commit**

```bash
git add resources/views/layouts/partials/flyout-panel.blade.php
git commit -m "feat: add flyout panel partial"
```

---

## Task 6: Update Header Partial

**Files:**
- Modify: `resources/views/layouts/partials/header.blade.php`

Remove the hamburger toggle. Add: organization name (left), ⌘K search trigger (center), theme toggle + notification bell (right). The user avatar/logout now lives in the Profile flyout on the icon rail.

**Step 1: Replace the file**

```blade
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
```

**Step 2: Commit**

```bash
git add resources/views/layouts/partials/header.blade.php
git commit -m "feat: update header with search trigger, theme toggle, and notifications"
```

---

## Task 7: Command Palette Partial + ViewComposer

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `resources/views/layouts/partials/command-palette.blade.php`

**Step 1: Update `AppServiceProvider.php`**

Add a ViewComposer so `$recentMeetings` is available to `layouts.app` whenever the user is authenticated and has an active organization.

```php
<?php

namespace App\Providers;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Policies\OrganizationPolicy;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Policies\ActionItemPolicy;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Policies\MinutesOfMeetingPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(MinutesOfMeeting::class, MinutesOfMeetingPolicy::class);
        Gate::policy(ActionItem::class, ActionItemPolicy::class);

        View::composer('layouts.app', function ($view) {
            if (Auth::check() && Auth::user()->current_organization_id) {
                $view->with('recentMeetings', MinutesOfMeeting::query()
                    ->where('organization_id', Auth::user()->current_organization_id)
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'title', 'meeting_date']));
            }
        });
    }
}
```

**Step 2: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 3: Create `resources/views/layouts/partials/command-palette.blade.php`**

The outer overlay closes on backdrop click. The inner modal closes on Escape (handled in Alpine init). Keyboard navigation: ↑↓ arrows, Enter to select.

```blade
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
```

**Step 4: Commit**

```bash
git add app/Providers/AppServiceProvider.php \
        resources/views/layouts/partials/command-palette.blade.php
git commit -m "feat: add command palette partial and ViewComposer for recent meetings"
```

---

## Task 8: FAB Partial

**Files:**
- Create: `resources/views/layouts/partials/fab.blade.php`

56px circle, gradient violet→blue, violet glow shadow. Click to rotate icon 45° and reveal two mini buttons (New Meeting, New Action Item) with staggered entrance.

**Step 1: Create the file**

```blade
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
```

**Step 2: Commit**

```bash
git add resources/views/layouts/partials/fab.blade.php
git commit -m "feat: add FAB partial with New Meeting and New Action Item"
```

---

## Task 9: Mobile Bottom Nav Partial

**Files:**
- Create: `resources/views/layouts/partials/mobile-bottom-nav.blade.php`

64px tall + `safe-area-inset-bottom`. 4 main items + "More" button that opens a bottom sheet with Settings, Theme toggle, and Logout.

**Step 1: Create the file**

```blade
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
```

**Step 2: Commit**

```bash
git add resources/views/layouts/partials/mobile-bottom-nav.blade.php
git commit -m "feat: add mobile bottom nav with bottom sheet"
```

---

## Task 10: Smoke Tests

**Files:**
- Create: `tests/Feature/Navigation/LayoutRenderTest.php`

**Step 1: Generate the test file**

```bash
php artisan make:test --pest Navigation/LayoutRenderTest
```

**Step 2: Replace the generated file contents**

```php
<?php

use App\Domain\Account\Models\User;

it('renders the app shell with appState Alpine component', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('x-data="appState"', false)
        ->assertSee('commandPaletteOpen', false)
        ->assertSee('cycleTheme', false)
        ->assertSee('fabExpanded', false);
});

it('renders the icon rail', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('activeFlyout', false)
        ->assertSee('Home', false)
        ->assertSee('Meetings', false)
        ->assertSee('Settings', false);
});

it('renders the command palette overlay', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('commandQuery', false)
        ->assertSee('commandInput', false);
});

it('renders the FAB', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('fabExpanded', false)
        ->assertSee('meetings/create', false);
});

it('renders theme toggle controls', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('cycleTheme', false);
});
```

**Step 3: Run tests**

```bash
php artisan test --compact --filter=LayoutRenderTest
```

Expected: 5 passing tests.

**Step 4: Commit**

```bash
git add tests/Feature/Navigation/LayoutRenderTest.php
git commit -m "test: add smoke tests for hybrid navigation layout"
```

---

## Task 11: Build, Pint, Full Test Suite & Cleanup

**Step 1: Run Pint on all modified PHP**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 2: Build frontend**

```bash
npm run build
```

Expected: exits 0.

**Step 3: Run full test suite**

```bash
php artisan test --compact
```

Expected: all tests passing (no regressions).

**Step 4: Delete old sidebar partial**

```bash
rm resources/views/layouts/partials/sidebar.blade.php
```

**Step 5: Final commit**

```bash
git add -A
git commit -m "feat: complete UI navigation redesign

- Replace 264px sidebar with 48px icon rail (pill, fixed)
- Add flyout panel (240px, per-group navigation)
- Add command palette (⌘K) with recent meetings and keyboard nav
- Add FAB with New Meeting and New Action Item mini buttons
- Add mobile bottom nav with bottom sheet (Settings, theme, logout)
- Add light/dark/system theme toggle with localStorage persistence
- Add violet/blue/teal color tokens to Tailwind @theme
- Delete legacy sidebar.blade.php"
```

---

## Reference: Key Design Values

| Component       | Key values |
|-----------------|-----------|
| Icon Rail       | `fixed left-3 top-3`, `w-12`, `rounded-3xl`, `height: calc(100vh - 24px)` |
| Flyout Panel    | `fixed left-[68px]`, `w-60`, `rounded-2xl`, animation 200ms ease-out |
| Content offset  | `md:ml-[72px]` on main wrapper |
| Command Palette | `max-w-lg`, `pt-[20vh]`, `max-height: 70vh`, scale+fade 150ms |
| FAB             | `w-14 h-14`, `bottom-6 right-6`, `gradient from-primary-500 to-secondary-500` |
| Mobile Nav      | `height: calc(64px + env(safe-area-inset-bottom))` |
| Primary color   | `#8B5CF6` (violet-500) |
| Secondary color | `#3B82F6` (blue-500) |
| Accent color    | `#2DD4BF` (teal-400) |
