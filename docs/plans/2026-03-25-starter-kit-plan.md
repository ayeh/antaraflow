# antaraFlow Starter Kit — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Extract antaraFlow's UI design system into a standalone HTML + Tailwind + Alpine.js starter kit inside `/starter-kit/`, ready to be used as a base template for any new system.

**Architecture:** Flat HTML files (no build step) using Tailwind CDN + Alpine.js CDN. Branding driven entirely by CSS custom properties in swappable preset files. Each page file is fully standalone — includes inline sidebar and header HTML with a comment block separator.

**Tech Stack:** HTML5, Tailwind CSS v4 CDN, Alpine.js v3 CDN + @alpinejs/collapse, Heroicons (inline SVG), no backend dependency.

---

## Task 1: Folder scaffold + base assets

**Files:**
- Create: `starter-kit/assets/app.css`
- Create: `starter-kit/assets/app.js`

**Step 1: Create the folder structure**

```bash
mkdir -p starter-kit/html/presets
mkdir -p starter-kit/assets
mkdir -p starter-kit/docs
```

**Step 2: Create `starter-kit/assets/app.css`**

```css
/* antaraFlow Starter Kit — Base CSS
   ============================================================
   Import a preset file BEFORE this file to set brand variables.
   Preset files live in html/presets/preset-*.css
   ============================================================ */

/* Tailwind v4 CDN is loaded in each HTML file via <script>.
   This file contains only custom brand variable overrides. */

:root {
    /* Brand shades — auto-generated from --brand-primary */
    --brand-p50:  color-mix(in srgb, var(--brand-primary)  5%, white);
    --brand-p100: color-mix(in srgb, var(--brand-primary) 10%, white);
    --brand-p200: color-mix(in srgb, var(--brand-primary) 20%, white);
    --brand-p300: color-mix(in srgb, var(--brand-primary) 40%, white);
    --brand-p400: color-mix(in srgb, var(--brand-primary) 60%, white);
    --brand-p500: color-mix(in srgb, var(--brand-primary) 80%, white);
    --brand-p600: var(--brand-primary);
    --brand-p700: color-mix(in srgb, var(--brand-primary) 85%, black);
    --brand-p800: color-mix(in srgb, var(--brand-primary) 70%, black);
    --brand-p900: color-mix(in srgb, var(--brand-primary) 15%, #0f172a);
}

/* Map Tailwind violet-* classes to brand variables */
.bg-violet-50  { background-color: var(--brand-p50)  !important; }
.bg-violet-100 { background-color: var(--brand-p100) !important; }
.bg-violet-200 { background-color: var(--brand-p200) !important; }
.bg-violet-500 { background-color: var(--brand-p500) !important; }
.bg-violet-600 { background-color: var(--brand-p600) !important; }
.bg-violet-700 { background-color: var(--brand-p700) !important; }
.hover\:bg-violet-50:hover  { background-color: var(--brand-p50)  !important; }
.hover\:bg-violet-100:hover { background-color: var(--brand-p100) !important; }
.hover\:bg-violet-700:hover { background-color: var(--brand-p700) !important; }

.text-violet-400 { color: var(--brand-p400) !important; }
.text-violet-500 { color: var(--brand-p500) !important; }
.text-violet-600 { color: var(--brand-p600) !important; }
.text-violet-700 { color: var(--brand-p700) !important; }
.hover\:text-violet-700:hover { color: var(--brand-p700) !important; }

.border-violet-200 { border-color: var(--brand-p200) !important; }
.border-violet-500 { border-color: var(--brand-p500) !important; }
.border-violet-600 { border-color: var(--brand-p600) !important; }
.focus\:border-violet-500:focus { border-color: var(--brand-p500) !important; }
.focus\:ring-violet-500:focus { --tw-ring-color: var(--brand-p500) !important; }

.dark .dark\:text-violet-300 { color: var(--brand-p300) !important; }
.dark .dark\:text-violet-400 { color: var(--brand-p400) !important; }
.dark .dark\:bg-violet-900\/10  { background-color: color-mix(in srgb, var(--brand-primary) 10%, transparent) !important; }
.dark .dark\:bg-violet-900\/20  { background-color: color-mix(in srgb, var(--brand-primary) 20%, transparent) !important; }
.dark .dark\:border-violet-500  { border-color: var(--brand-p500) !important; }
```

**Step 3: Create `starter-kit/assets/app.js`**

```javascript
// antaraFlow Starter Kit — Alpine.js App State

document.addEventListener('alpine:init', () => {
    Alpine.data('appState', () => ({
        // Sidebar
        sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
        activeFlyout: null,

        // Theme: 'light' | 'dark' | 'system'
        theme: localStorage.getItem('theme') || 'system',

        init() {
            this.applyTheme();
            this.$watch('theme', () => this.applyTheme());
        },

        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
            localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
            if (this.sidebarCollapsed) { this.activeFlyout = null; }
        },

        cycleTheme() {
            const order = ['light', 'dark', 'system'];
            const idx = order.indexOf(this.theme);
            this.theme = order[(idx + 1) % 3];
            localStorage.setItem('theme', this.theme);
        },

        applyTheme() {
            const isDark = this.theme === 'dark' ||
                (this.theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', isDark);
        },
    }));
});
```

**Step 4: Verify files exist**

```bash
ls starter-kit/assets/
# Expected: app.css  app.js
```

**Step 5: Commit**

```bash
git add starter-kit/
git commit -m "feat: scaffold starter-kit folder and base assets"
```

---

## Task 2: Branding presets

**Files:**
- Create: `starter-kit/html/presets/preset-blank.css`
- Create: `starter-kit/html/presets/preset-antaranote.css`

**Step 1: Create `preset-blank.css`**

```css
/* antaraFlow Starter Kit — Blank/Neutral Preset
   ============================================================
   CUSTOMISE THIS FILE to rebrand for a new system.
   Only change the values below — the rest auto-generates.
   ============================================================ */

:root {
    /* 1. Primary brand color — change this one value to rebrand */
    --brand-primary: #7c3aed;           /* Default: violet */

    /* 2. Sidebar */
    --brand-sidebar-bg: #ffffff;        /* white sidebar for neutral preset */
    --brand-sidebar-text: #334155;      /* slate-700 */

    /* 3. Typography */
    --brand-font-heading: 'Inter', sans-serif;
    --brand-font-body:    'Inter', sans-serif;

    /* 4. Shape */
    --brand-radius: 0.75rem;            /* rounded-xl */
}

/* Apply heading font */
h1, h2, h3, h4, h5, h6 {
    font-family: var(--brand-font-heading);
}
body {
    font-family: var(--brand-font-body);
}
```

**Step 2: Create `preset-antaranote.css`**

```css
/* antaraFlow Starter Kit — antaraNote Brand Preset
   ============================================================
   Teal brand identity for antaraNote (meeting management SaaS).
   Fonts: Plus Jakarta Sans (headings) + Inter (body)
   ============================================================ */

@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700&family=Inter:wght@400;500&display=swap');

:root {
    --brand-primary:        #0D7377;    /* Nusantara Teal */
    --brand-sidebar-bg:     #095153;    /* Deep Teal */
    --brand-sidebar-text:   #ffffff;

    --brand-font-heading: 'Plus Jakarta Sans', sans-serif;
    --brand-font-body:    'Inter', sans-serif;

    --brand-radius: 0.75rem;
}

h1, h2, h3, h4, h5, h6 {
    font-family: var(--brand-font-heading);
}
body {
    font-family: var(--brand-font-body);
}
```

**Step 3: Commit**

```bash
git add starter-kit/html/presets/
git commit -m "feat: add blank and antaranote brand presets"
```

---

## Task 3: `_sidebar.html` partial

**Files:**
- Create: `starter-kit/html/_sidebar.html`

**Step 1: Create the file**

This is a standalone HTML fragment (no `<html>` wrapper) to be copy-pasted into each page inside the `x-data="appState"` div.

```html
<!-- ============================================================
     SIDEBAR PARTIAL
     Copy this block into your page inside x-data="appState"
     Requires: Alpine.js, app.js (appState), app.css
     ============================================================ -->
<nav
    aria-label="Main navigation"
    :class="sidebarCollapsed ? 'w-14' : 'w-56'"
    class="fixed left-3 top-3 bottom-3 z-50 flex flex-col
           bg-white/90 dark:bg-slate-800/90 backdrop-blur-xl
           shadow-xl ring-1 ring-black/5 dark:ring-white/10 rounded-2xl
           transition-all duration-500 ease-in-out overflow-hidden hidden md:flex"
>
    <!-- Brand + Toggle -->
    <div class="flex items-center px-3 h-16 shrink-0"
         :class="sidebarCollapsed ? 'justify-center' : 'justify-between'">

        <!-- Logo / App name — replace with your own -->
        <a href="#" x-show="!sidebarCollapsed"
           x-transition:enter="transition ease-out duration-200 delay-100"
           x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
           x-transition:leave="transition ease-in duration-100"
           x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
           class="flex items-center gap-2 min-w-0">
            <!-- OPTION A: Image logo -->
            <!-- <img src="/path/to/logo.svg" alt="App Name" class="h-7 w-auto max-w-[140px] object-contain"> -->
            <!-- OPTION B: Text logo (default) -->
            <span class="text-violet-600 dark:text-violet-400 font-black text-sm leading-none tracking-tighter shrink-0">AB</span>
            <span class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate">AppName</span>
        </a>

        <!-- Collapse toggle -->
        <button @click.stop="toggleSidebar()"
                class="shrink-0 flex items-center justify-center w-7 h-7 rounded-xl
                       bg-violet-600 hover:bg-violet-700 text-white transition-colors"
                :title="sidebarCollapsed ? 'Expand' : 'Collapse'">
            <svg class="w-3.5 h-3.5 transition-transform duration-300" :class="sidebarCollapsed ? 'rotate-180' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
            </svg>
        </button>
    </div>

    <!-- Nav Items -->
    <div class="flex-1 flex flex-col gap-0.5 px-2 overflow-y-auto">

        <!-- Nav item — ACTIVE state example -->
        <div class="relative">
            <a href="#"
               class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl transition-all duration-150
                      bg-violet-50 dark:bg-violet-900/20 text-violet-600 dark:text-violet-400 font-medium">
                <span class="absolute left-0 top-2 bottom-2 w-0.5 rounded-r-full bg-violet-600 dark:bg-violet-400"></span>
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span x-show="!sidebarCollapsed"
                      x-transition:enter="transition ease-out duration-150 delay-75"
                      x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in duration-100"
                      x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                      class="text-sm truncate">Dashboard</span>
            </a>
        </div>

        <!-- Nav item — INACTIVE state example -->
        <div class="relative">
            <a href="#"
               class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl transition-all duration-150
                      text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700 hover:shadow-sm hover:text-slate-800 dark:hover:text-slate-100">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <span x-show="!sidebarCollapsed"
                      x-transition:enter="transition ease-out duration-150 delay-75"
                      x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in duration-100"
                      x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                      class="text-sm truncate">Items</span>
            </a>
        </div>

        <!-- Nav item with badge -->
        <div class="relative">
            <a href="#"
               class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl transition-all duration-150
                      text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700 hover:shadow-sm hover:text-slate-800 dark:hover:text-slate-100">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
                <span x-show="!sidebarCollapsed"
                      x-transition:enter="transition ease-out duration-150 delay-75"
                      x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in duration-100"
                      x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                      class="text-sm truncate">Insights</span>
                <span x-show="!sidebarCollapsed"
                      class="ml-auto inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-red-500 text-white min-w-[18px]">3</span>
            </a>
        </div>

        <!-- Divider -->
        <div class="my-1 border-t border-slate-200 dark:border-slate-700"></div>

        <!-- Settings button -->
        <div class="relative">
            <button @click.stop="activeFlyout = activeFlyout === 'settings' ? null : 'settings'"
                    class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl transition-all duration-150
                           text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700 hover:shadow-sm hover:text-slate-800 dark:hover:text-slate-100">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span x-show="!sidebarCollapsed"
                      x-transition:enter="transition ease-out duration-150 delay-75"
                      x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                      x-transition:leave="transition ease-in duration-100"
                      x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                      class="text-sm truncate flex-1 text-left">Settings</span>
                <svg x-show="!sidebarCollapsed" class="w-3.5 h-3.5 shrink-0 text-slate-400"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>
        </div>

    </div>

    <!-- User Profile (bottom) -->
    <div class="shrink-0 px-2 py-3 border-t border-slate-200 dark:border-slate-700">
        <button @click.stop="activeFlyout = activeFlyout === 'profile' ? null : 'profile'"
                class="relative flex items-center gap-3 w-full px-2 py-2 rounded-xl
                       hover:bg-white dark:hover:bg-slate-700 hover:shadow-sm transition-all duration-150">
            <div class="shrink-0 flex items-center justify-center w-8 h-8 rounded-full
                        bg-violet-600 dark:bg-violet-500 text-white text-xs font-bold">JD</div>
            <div x-show="!sidebarCollapsed"
                 x-transition:enter="transition ease-out duration-150 delay-75"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="flex-1 min-w-0 text-left">
                <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">John Doe</p>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 truncate">john@example.com</p>
            </div>
        </button>
    </div>
</nav>

<!-- Profile flyout -->
<div x-show="activeFlyout === 'profile'"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-1"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-1"
     @click.outside="activeFlyout = null"
     :style="{ left: sidebarCollapsed ? '68px' : '236px', bottom: '12px' }"
     class="fixed z-40 w-60 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl py-3">
    <div class="px-4 py-2 mb-1">
        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">John Doe</p>
        <p class="text-xs text-slate-500 dark:text-slate-400 truncate">john@example.com</p>
    </div>
    <div class="mx-2 space-y-0.5">
        <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm
                           text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            Profile
        </a>
    </div>
    <div class="border-t border-slate-100 dark:border-slate-700 mt-2 pt-2 mx-2 space-y-0.5">
        <button @click="cycleTheme()"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-left
                       text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
            <span class="w-4 h-4 flex items-center justify-center text-slate-400"
                  x-text="theme === 'light' ? '☀️' : theme === 'dark' ? '🌙' : '💻'"></span>
            <span x-text="'Theme: ' + (theme === 'light' ? 'Light' : theme === 'dark' ? 'Dark' : 'System')"></span>
        </button>
        <button class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-sm
                       text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Logout
        </button>
    </div>
</div>
<!-- END SIDEBAR PARTIAL -->
```

**Step 2: Commit**

```bash
git add starter-kit/html/_sidebar.html
git commit -m "feat: add sidebar partial to starter kit"
```

---

## Task 4: `_header.html` partial

**Files:**
- Create: `starter-kit/html/_header.html`

**Step 1: Create the file**

```html
<!-- ============================================================
     HEADER PARTIAL
     Copy this block inside the content area div (after sidebar offset)
     ============================================================ -->
<header class="sticky top-0 z-30 bg-white/80 dark:bg-slate-800/80 backdrop-blur-sm
               border-b border-slate-200 dark:border-slate-700 px-6 h-16
               flex items-center gap-3">

    <!-- Left: org name / breadcrumb -->
    <div class="flex-1 min-w-0 flex items-center gap-2">
        <span class="text-sm font-medium text-slate-600 dark:text-slate-400 truncate">
            Organisation Name
        </span>
    </div>

    <!-- Search trigger (⌘K) -->
    <button class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg
                   bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600
                   text-slate-500 dark:text-slate-400 text-sm
                   hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <span>Search</span>
        <kbd class="ml-1 text-xs px-1.5 py-0.5 rounded bg-slate-200 dark:bg-slate-600 text-slate-500 dark:text-slate-400 font-mono">⌘K</kbd>
    </button>

    <!-- Theme toggle -->
    <button @click="cycleTheme()"
            class="flex items-center justify-center w-8 h-8 rounded-lg
                   text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
        <span x-show="theme === 'light'">☀️</span>
        <span x-show="theme === 'dark'">🌙</span>
        <span x-show="theme === 'system'">💻</span>
    </button>

    <!-- Notification bell -->
    <div x-data="{ open: false, count: 3 }" class="relative">
        <button @click="open = !open"
                class="relative flex items-center justify-center w-8 h-8 rounded-lg
                       text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <span x-show="count > 0" x-text="count"
                  class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center leading-none"></span>
        </button>
        <div x-show="open" @click.outside="open = false" x-transition
             class="absolute right-0 mt-2 w-72 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-lg z-50 py-2">
            <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-700">
                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">Notifications</span>
            </div>
            <div class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400">No new notifications</div>
        </div>
    </div>

</header>
<!-- END HEADER PARTIAL -->
```

**Step 2: Commit**

```bash
git add starter-kit/html/_header.html
git commit -m "feat: add header partial to starter kit"
```

---

## Task 5: `login.html`

**Files:**
- Create: `starter-kit/html/login.html`

**Step 1: Create the file**

The login page is a standalone full-page layout (no sidebar). Uses the app shell HTML boilerplate with Alpine `appState`.

```html
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — AppName</title>
    <!-- Preset (swap to change branding) -->
    <link rel="stylesheet" href="presets/preset-blank.css">
    <!-- Tailwind v4 CDN -->
    <script src="https://cdn.tailwindcss.com/4.0.0"></script>
    <!-- Brand variable overrides -->
    <link rel="stylesheet" href="../assets/app.css">
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="../assets/app.js"></script>
</head>
<body class="h-full bg-slate-200 dark:bg-slate-950 text-slate-900 dark:text-slate-100"
      x-data="appState" x-init="init()">

    <div class="min-h-full flex items-center justify-center py-12 px-4">
        <div class="w-full max-w-sm">

            <!-- Logo -->
            <div class="text-center mb-8">
                <!-- Replace with <img> for image logo -->
                <div class="inline-flex items-center gap-2 mb-4">
                    <span class="text-violet-600 dark:text-violet-400 font-black text-2xl leading-none tracking-tighter">AB</span>
                    <span class="text-xl font-bold text-slate-800 dark:text-slate-100">AppName</span>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Sign in to your account</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Welcome back</p>
            </div>

            <!-- Card -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-700 p-8">
                <form class="space-y-5">

                    <!-- Email -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                            Email address
                        </label>
                        <input type="email" placeholder="you@example.com"
                               class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                                      bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100
                                      placeholder-slate-400 dark:placeholder-slate-500 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500
                                      transition-colors">
                    </div>

                    <!-- Password -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                                Password
                            </label>
                            <a href="#" class="text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700">
                                Forgot password?
                            </a>
                        </div>
                        <input type="password" placeholder="••••••••"
                               class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                                      bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100
                                      placeholder-slate-400 dark:placeholder-slate-500 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500
                                      transition-colors">
                    </div>

                    <!-- Validation error (show when needed) -->
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl px-4 py-3 text-sm text-red-600 dark:text-red-400" style="display:none">
                        These credentials do not match our records.
                    </div>

                    <!-- Submit -->
                    <button type="submit"
                            class="w-full py-2.5 px-4 rounded-xl bg-violet-600 hover:bg-violet-700
                                   text-white text-sm font-semibold transition-colors focus:outline-none
                                   focus:ring-2 focus:ring-violet-500 focus:ring-offset-2">
                        Sign in
                    </button>

                </form>
            </div>

            <!-- Footer link -->
            <p class="text-center text-sm text-slate-500 dark:text-slate-400 mt-6">
                Don't have an account?
                <a href="#" class="text-violet-600 dark:text-violet-400 hover:text-violet-700 font-medium">Sign up</a>
            </p>

        </div>
    </div>

</body>
</html>
```

**Step 2: Commit**

```bash
git add starter-kit/html/login.html
git commit -m "feat: add login page to starter kit"
```

---

## Task 6: `dashboard.html`

**Files:**
- Create: `starter-kit/html/dashboard.html`

**Step 1: Create the file**

Full page with sidebar + header + dashboard content. Use the HTML boilerplate from login.html, but add the sidebar layout wrapper.

```html
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — AppName</title>
    <link rel="stylesheet" href="presets/preset-blank.css">
    <script src="https://cdn.tailwindcss.com/4.0.0"></script>
    <link rel="stylesheet" href="../assets/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="../assets/app.js"></script>
</head>
<body class="h-full bg-slate-200 dark:bg-slate-950 text-slate-900 dark:text-slate-100">

<div x-data="appState" x-init="init()" class="min-h-full">

    <!-- SIDEBAR (paste _sidebar.html content here) -->
    <!-- [[ SIDEBAR PARTIAL ]] -->

    <!-- Content area -->
    <div :class="sidebarCollapsed ? 'md:ml-[5rem]' : 'md:ml-[15.5rem]'"
         class="flex flex-col min-h-screen bg-slate-50 dark:bg-slate-900
                md:mt-3 md:[clip-path:inset(0_0_0_0_round_1rem_0_0_0)]
                transition-all duration-300 ease-in-out">

        <!-- HEADER (paste _header.html content here) -->
        <!-- [[ HEADER PARTIAL ]] -->

        <!-- Page content -->
        <main class="flex-1 p-6 space-y-6">

            <!-- Page header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Dashboard</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Wednesday, 25 March 2026</p>
                </div>
                <button class="inline-flex items-center gap-2 px-4 py-2 rounded-xl
                               bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New Item
                </button>
            </div>

            <!-- Stat cards (4-up grid) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Stat card template — repeat 4x with different values/icons -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Items</span>
                        <div class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-slate-900 dark:text-slate-100">128</p>
                    <p class="text-xs text-green-600 dark:text-green-400 mt-1">↑ 12% from last month</p>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400">Active</span>
                        <div class="w-9 h-9 rounded-xl bg-green-50 dark:bg-green-900/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-slate-900 dark:text-slate-100">84</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">65% of total</p>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400">Pending</span>
                        <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-900/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-slate-900 dark:text-slate-100">31</p>
                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-1">Needs attention</p>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400">Users</span>
                        <div class="w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-900/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-slate-900 dark:text-slate-100">24</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">3 added this week</p>
                </div>
            </div>

            <!-- Recent activity -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">Recent Activity</h2>
                    <a href="#" class="text-sm text-violet-600 dark:text-violet-400 hover:text-violet-700">View all</a>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-700">
                    <!-- Activity item — repeat -->
                    <div class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                        <div class="shrink-0 w-8 h-8 rounded-full bg-violet-600 text-white text-xs font-bold flex items-center justify-center">JD</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-slate-800 dark:text-slate-200">
                                <span class="font-medium">John Doe</span> updated the project status
                            </p>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">2 hours ago</p>
                        </div>
                        <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800">
                            Completed
                        </span>
                    </div>
                    <div class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                        <div class="shrink-0 w-8 h-8 rounded-full bg-amber-500 text-white text-xs font-bold flex items-center justify-center">SA</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-slate-800 dark:text-slate-200">
                                <span class="font-medium">Sarah Ali</span> created a new task
                            </p>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">5 hours ago</p>
                        </div>
                        <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                            Pending
                        </span>
                    </div>
                    <!-- Empty state (show when no activity) -->
                    <!--
                    <div class="py-12 text-center">
                        <svg class="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <p class="text-sm font-medium text-slate-600 dark:text-slate-400">No activity yet</p>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Actions will appear here</p>
                    </div>
                    -->
                </div>
            </div>

        </main>
    </div>
</div>

</body>
</html>
```

**Step 2: Commit**

```bash
git add starter-kit/html/dashboard.html
git commit -m "feat: add dashboard page to starter kit"
```

---

## Task 7: `list.html`

**Files:**
- Create: `starter-kit/html/list.html`

**Step 1: Create the file**

Same shell as dashboard.html. Content section includes: page header, search + filter bar, filter drawer (Alpine), data table, pagination.

Key patterns to include:
- Filter drawer using `x-show` + `x-collapse` from `@alpinejs/collapse`
- Table with `<thead>` using `bg-slate-50 dark:bg-slate-700/50` and `text-xs uppercase tracking-wider`
- Status badge variants (green/amber/red/slate)
- Action dropdown per row using Alpine `x-data="{ open: false }"`
- Pagination: prev/next + page numbers with active state

Use the same `<!DOCTYPE html>` shell from Task 6. Replace the `<main>` content with:

```html
<main class="flex-1 p-6 space-y-4">

    <!-- Page header -->
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Items</h1>
        <a href="form.html"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl
                  bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Item
        </a>
    </div>

    <!-- Search + filter bar -->
    <div x-data="{ filterOpen: false }" class="space-y-3">
        <div class="flex items-center gap-3">
            <div class="flex-1 relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="search" placeholder="Search items..."
                       class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                              bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100
                              placeholder-slate-400 dark:placeholder-slate-500 text-sm
                              focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
            </div>
            <button @click="filterOpen = !filterOpen"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                           bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-sm font-medium
                           hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                </svg>
                Filters
            </button>
        </div>

        <!-- Filter drawer -->
        <div x-show="filterOpen" x-collapse
             class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Status</label>
                    <select class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600
                                   bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 text-sm
                                   focus:outline-none focus:ring-2 focus:ring-violet-500 transition-colors">
                        <option>All statuses</option>
                        <option>Active</option>
                        <option>Pending</option>
                        <option>Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Category</label>
                    <select class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600
                                   bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 text-sm
                                   focus:outline-none focus:ring-2 focus:ring-violet-500 transition-colors">
                        <option>All categories</option>
                        <option>Category A</option>
                        <option>Category B</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button class="w-full px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600
                                   text-sm text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                        Clear filters
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden sm:table-cell">Category</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden md:table-cell">Created</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                <!-- Row (active) -->
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="px-4 py-3">
                        <a href="show.html" class="font-medium text-slate-900 dark:text-slate-100 hover:text-violet-600 dark:hover:text-violet-400 transition-colors">Item Alpha</a>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Item description or subtitle here</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                     bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800">
                            Active
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400 hidden sm:table-cell">Category A</td>
                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs hidden md:table-cell">20 Mar 2026</td>
                    <td class="px-4 py-3 text-right">
                        <div x-data="{ open: false }" class="relative inline-block">
                            <button @click="open = !open" @click.outside="open = false"
                                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                </svg>
                            </button>
                            <div x-show="open" x-transition
                                 class="absolute right-0 mt-1 w-40 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-lg z-10 py-1">
                                <a href="show.html" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    View
                                </a>
                                <a href="form.html" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Edit
                                </a>
                                <div class="border-t border-slate-100 dark:border-slate-700 my-1"></div>
                                <button class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </td>
                </tr>
                <!-- Row (pending) — copy row above, change badge to amber -->
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="px-4 py-3">
                        <a href="show.html" class="font-medium text-slate-900 dark:text-slate-100 hover:text-violet-600 dark:hover:text-violet-400 transition-colors">Item Beta</a>
                        <p class="text-xs text-slate-400 dark:text-slate-500 mt-0.5">Another item description</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                     bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                            Pending
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-600 dark:text-slate-400 hidden sm:table-cell">Category B</td>
                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400 text-xs hidden md:table-cell">18 Mar 2026</td>
                    <td class="px-4 py-3 text-right">
                        <!-- action dropdown same as above -->
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="border-t border-slate-200 dark:border-slate-700 px-4 py-3 flex items-center justify-between">
            <p class="text-sm text-slate-500 dark:text-slate-400">Showing 1–10 of 128</p>
            <div class="flex items-center gap-1">
                <button class="px-2.5 py-1.5 rounded-lg text-sm text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 disabled:opacity-40 transition-colors" disabled>
                    ← Prev
                </button>
                <button class="px-2.5 py-1.5 rounded-lg text-sm font-medium bg-violet-600 text-white">1</button>
                <button class="px-2.5 py-1.5 rounded-lg text-sm text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">2</button>
                <button class="px-2.5 py-1.5 rounded-lg text-sm text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">3</button>
                <button class="px-2.5 py-1.5 rounded-lg text-sm text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                    Next →
                </button>
            </div>
        </div>
    </div>

</main>
```

**Step 2: Commit**

```bash
git add starter-kit/html/list.html
git commit -m "feat: add list/index page to starter kit"
```

---

## Task 8: `show.html`

**Files:**
- Create: `starter-kit/html/show.html`

**Step 1: Create the file**

Same shell. Content section includes: back link, page title + status badge, action buttons, 2-column layout (main 2/3 + sidebar 1/3), tabs, related items in sidebar.

Replace `<main>` content with:

```html
<main class="flex-1 p-6 space-y-4">

    <!-- Back link + title row -->
    <div>
        <a href="list.html" class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors mb-3">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Items
        </a>
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Item Alpha</h1>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium
                             bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-800">
                    Active
                </span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="form.html"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600
                          text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800
                          hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit
                </a>
                <button class="inline-flex items-center gap-2 px-4 py-2 rounded-xl
                               bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold transition-colors">
                    Primary Action
                </button>
            </div>
        </div>
    </div>

    <!-- 2-column layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Main content (2/3) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Tabs -->
            <div x-data="{ tab: 'details' }"
                 class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <!-- Tab nav -->
                <div class="border-b border-slate-200 dark:border-slate-700 px-4">
                    <nav class="flex gap-0 -mb-px">
                        <button @click="tab = 'details'"
                                :class="tab === 'details' ? 'border-violet-600 text-violet-600 dark:text-violet-400 dark:border-violet-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300'"
                                class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                            Details
                        </button>
                        <button @click="tab = 'activity'"
                                :class="tab === 'activity' ? 'border-violet-600 text-violet-600 dark:text-violet-400 dark:border-violet-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300'"
                                class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                            Activity
                        </button>
                        <button @click="tab = 'notes'"
                                :class="tab === 'notes' ? 'border-violet-600 text-violet-600 dark:text-violet-400 dark:border-violet-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:border-slate-300'"
                                class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                            Notes
                        </button>
                    </nav>
                </div>
                <!-- Tab content -->
                <div class="p-6">
                    <div x-show="tab === 'details'">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Category</dt>
                                <dd class="text-sm text-slate-900 dark:text-slate-100">Category A</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Created</dt>
                                <dd class="text-sm text-slate-900 dark:text-slate-100">20 March 2026</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Description</dt>
                                <dd class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
                                    This is the full description of the item. It provides context, details, and any relevant information that users need to understand what this item is about.
                                </dd>
                            </div>
                        </dl>
                    </div>
                    <div x-show="tab === 'activity'">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Activity timeline goes here.</p>
                    </div>
                    <div x-show="tab === 'notes'">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Notes section goes here.</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- Sidebar (1/3) -->
        <div class="space-y-4">

            <!-- Info card -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Details</h3>
                <dl class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Owner</dt>
                        <dd class="text-xs font-medium text-slate-700 dark:text-slate-300">John Doe</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Priority</dt>
                        <dd>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                         bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-800">
                                High
                            </span>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Due date</dt>
                        <dd class="text-xs font-medium text-slate-700 dark:text-slate-300">31 Mar 2026</dd>
                    </div>
                </dl>
            </div>

            <!-- Related items card -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-3">Related</h3>
                <div class="space-y-2">
                    <a href="#" class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                        <div class="w-7 h-7 rounded-lg bg-violet-50 dark:bg-violet-900/20 flex items-center justify-center shrink-0">
                            <svg class="w-3.5 h-3.5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-slate-700 dark:text-slate-300 truncate">Related Item One</p>
                            <p class="text-[10px] text-slate-400 dark:text-slate-500">15 Mar 2026</p>
                        </div>
                    </a>
                </div>
            </div>

        </div>
    </div>

</main>
```

**Step 2: Commit**

```bash
git add starter-kit/html/show.html
git commit -m "feat: add show/detail page to starter kit"
```

---

## Task 9: `form.html`

**Files:**
- Create: `starter-kit/html/form.html`

**Step 1: Create the file**

Same shell. Content section includes: page header (create/edit), sectioned form card, all input types, validation states, cancel + submit buttons.

Replace `<main>` content with:

```html
<main class="flex-1 p-6">
    <div class="max-w-2xl mx-auto space-y-4">

        <!-- Page header -->
        <div class="flex items-center justify-between">
            <div>
                <a href="list.html" class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors mb-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back
                </a>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Create Item</h1>
            </div>
        </div>

        <form class="space-y-6">

            <!-- Section: Basic Info -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-4">Basic Information</h2>
                <div class="space-y-4">

                    <!-- Text input (normal) -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                            Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" placeholder="Enter name"
                               class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                                      bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100
                                      placeholder-slate-400 dark:placeholder-slate-500 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                    </div>

                    <!-- Text input (with validation error) -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                            Slug <span class="text-red-500">*</span>
                        </label>
                        <input type="text" placeholder="item-slug" value=""
                               class="w-full px-3 py-2.5 rounded-xl border border-red-300 dark:border-red-600
                                      bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100
                                      placeholder-slate-400 dark:placeholder-slate-500 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors">
                        <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">The slug field is required.</p>
                    </div>

                    <!-- Select -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Category</label>
                        <select class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                                       bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 text-sm
                                       focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                            <option value="">Select a category</option>
                            <option>Category A</option>
                            <option>Category B</option>
                            <option>Category C</option>
                        </select>
                    </div>

                    <!-- Textarea -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Description</label>
                        <textarea rows="4" placeholder="Enter description..."
                                  class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                                         bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100
                                         placeholder-slate-400 dark:placeholder-slate-500 text-sm
                                         focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors resize-y"></textarea>
                    </div>

                </div>
            </div>

            <!-- Section: Settings -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-slate-100 mb-4">Settings</h2>
                <div class="space-y-4">

                    <!-- Date input -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">Due Date</label>
                        <input type="date"
                               class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                                      bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 text-sm
                                      focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-colors">
                    </div>

                    <!-- Toggle -->
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Active</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Make this item visible</p>
                        </div>
                        <button type="button" x-data="{ on: true }" @click="on = !on"
                                :class="on ? 'bg-violet-600' : 'bg-slate-200 dark:bg-slate-600'"
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2">
                            <span :class="on ? 'translate-x-6' : 'translate-x-1'"
                                  class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"></span>
                        </button>
                    </div>

                </div>
            </div>

            <!-- Form actions -->
            <div class="flex items-center justify-end gap-3">
                <a href="list.html"
                   class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600
                          text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800
                          hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="px-6 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700
                               text-white text-sm font-semibold transition-colors
                               focus:outline-none focus:ring-2 focus:ring-violet-500 focus:ring-offset-2">
                    Create Item
                </button>
            </div>

        </form>
    </div>
</main>
```

**Step 2: Commit**

```bash
git add starter-kit/html/form.html
git commit -m "feat: add create/edit form page to starter kit"
```

---

## Task 10: Documentation files

**Files:**
- Create: `starter-kit/docs/DESIGN-SYSTEM.md`
- Create: `starter-kit/docs/COMPONENT-GUIDE.md`
- Create: `starter-kit/docs/BRANDING-GUIDE.md`

**Step 1: Create `DESIGN-SYSTEM.md`**

Cover all design tokens extracted from antaraFlow:
- Color palette: slate-* neutrals + violet-* brand + status colors (green/amber/red/sky)
- Dark mode: always use `dark:bg-slate-*`, never `dark:bg-gray-*`
- Typography scale: display(36px) → h1(28px) → h2(22px) → h3(18px) → body(14px) → small(12px)
- Spacing: p-6 for page/card padding, gap-3/gap-4 for grids
- Border radius: `rounded-xl` buttons/cards, `rounded-2xl` sidebar/flyouts, `rounded-full` badges/pills
- Card pattern: `bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6`
- Body bg: `bg-slate-200 dark:bg-slate-950`
- Content area: `bg-slate-50 dark:bg-slate-900`
- Status badge patterns: green/amber/red/sky with bg/text/border for both light+dark

**Step 2: Create `COMPONENT-GUIDE.md`**

Include ready-to-copy code snippets for:
- Primary button, secondary button, danger button
- Green/amber/red/slate badge
- Card
- Stat card
- Table header row
- Form input (normal, error, disabled)
- Form select
- Form textarea
- Toggle switch
- Empty state
- Alert (success, error, warning)
- Page header with CTA
- Back link

**Step 3: Create `BRANDING-GUIDE.md`**

Step-by-step rebrand checklist:
1. Open `html/presets/preset-blank.css`
2. Change `--brand-primary` to your colour hex
3. Optionally change `--brand-sidebar-bg` if you want a different sidebar colour from primary
4. Replace the Google Fonts import URL with your font choices (or remove for system fonts)
5. Change `--brand-font-heading` and `--brand-font-body` to match
6. Find `AB` initials in sidebar and replace with your app initials or swap in `<img>` logo
7. Find `AppName` text and replace with your app name

**Step 4: Commit**

```bash
git add starter-kit/docs/
git commit -m "feat: add design system, component guide, and branding guide docs"
```

---

## Task 11: `README.md`

**Files:**
- Create: `starter-kit/README.md`

**Step 1: Create the file**

Content:
- What this kit is (1 paragraph)
- File map table
- How to use (open any `.html` file in browser; or integrate — just copy `<link>` and `<script>` tags)
- Tech dependencies (Tailwind CDN link, Alpine CDN link — copy from any HTML file head)
- How to rebrand (point to `BRANDING-GUIDE.md`)
- How to add nav items (explain the sidebar pattern)
- How to create a new page (copy `dashboard.html`, replace `<main>` content)
- Attribution note

**Step 2: Commit**

```bash
git add starter-kit/README.md
git commit -m "feat: add starter kit README"
```

---

## Task 12: Verification

**Step 1: Check all files exist**

```bash
ls starter-kit/
ls starter-kit/html/
ls starter-kit/html/presets/
ls starter-kit/assets/
ls starter-kit/docs/
```

Expected output:
```
starter-kit/
  README.md
  assets/app.css  assets/app.js
  docs/DESIGN-SYSTEM.md  COMPONENT-GUIDE.md  BRANDING-GUIDE.md
  html/login.html  dashboard.html  list.html  show.html  form.html
       _sidebar.html  _header.html
  html/presets/preset-blank.css  preset-antaranote.css
```

**Step 2: Check no `dark:bg-gray-*` violations**

```bash
grep -r "dark:bg-gray-" starter-kit/html/
# Expected: no output (zero matches)
```

**Step 3: Check no `bg-purple-*` violations**

```bash
grep -r "bg-purple-" starter-kit/html/
# Expected: no output (zero matches)
```

**Step 4: Check all buttons use `rounded-xl`**

```bash
grep -c "rounded-xl" starter-kit/html/form.html
# Expected: number > 0
```

**Step 5: Open pages visually in browser**

```bash
open starter-kit/html/login.html
open starter-kit/html/dashboard.html
open starter-kit/html/list.html
open starter-kit/html/show.html
open starter-kit/html/form.html
```

Verify visually:
- [ ] Sidebar renders and collapses
- [ ] Dark mode toggle works (header button)
- [ ] Filter drawer opens/closes on list page
- [ ] Tabs switch on show page
- [ ] Toggle switch works on form page
- [ ] Action dropdown opens on list page
- [ ] No visible `{{ }}` template syntax leaked

**Step 6: Final commit**

```bash
git add starter-kit/
git commit -m "feat: complete antaraFlow starter kit extraction"
```
