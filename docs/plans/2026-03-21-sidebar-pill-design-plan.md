# Sidebar Pill Design Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Redesign sidebar into a floating pill card with purple filled toggle button and rounded top-left corner on main content.

**Architecture:** Three-file change — sidebar shape/button, flyout left-offset corrections, app layout background/margin/corner. No backend, no JS changes.

**Tech Stack:** Blade, Tailwind CSS v4, Alpine.js

---

### Task 1: Update sidebar to floating pill shape + purple toggle button

**Files:**
- Modify: `resources/views/layouts/partials/sidebar.blade.php`

**Step 1: Change `<nav>` position, shape, background, shadow**

Replace the `<nav>` opening tag classes. Current:
```
fixed left-0 top-0 z-50 flex flex-col h-screen
bg-slate-100 dark:bg-slate-800/60
border-r border-slate-200 dark:border-slate-700
transition-all duration-300 ease-in-out overflow-hidden
```

New:
```
fixed left-3 top-3 bottom-3 z-50 flex flex-col
bg-white dark:bg-slate-800
shadow-lg rounded-2xl
transition-all duration-300 ease-in-out overflow-hidden
```

**Step 2: Replace toggle button with purple filled badge**

Current button markup (lines ~79-89):
```html
<button
    @click.stop="toggleSidebar()"
    class="shrink-0 p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200
           hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors"
    :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
    :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
>
    <svg class="w-4 h-4 transition-transform duration-300" :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
    </svg>
</button>
```

Replace with:
```html
<button
    @click.stop="toggleSidebar()"
    class="shrink-0 flex items-center justify-center w-7 h-7 rounded-xl
           bg-violet-600 hover:bg-violet-700 text-white transition-colors"
    :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
    :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
>
    <svg class="w-3.5 h-3.5 transition-transform duration-300" :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
    </svg>
</button>
```

**Step 3: Verify visually in browser**

- Sidebar should float with gap on all sides (top, bottom, left)
- Toggle button should be a purple filled square badge
- Collapsed: `>>` chevrons, Expanded: `<<` chevrons
- Rounded corners visible on sidebar card
- Shadow visible around the pill

**Step 4: Run Pint**

```bash
vendor/bin/pint resources/views/layouts/partials/sidebar.blade.php --format agent
```

**Step 5: Commit**

```bash
git add resources/views/layouts/partials/sidebar.blade.php
git commit -m "feat: sidebar floating pill design + purple toggle button"
```

---

### Task 2: Fix flyout left-offset positions

With the sidebar now at `left-3` (12px), its right edge positions have changed:
- Collapsed: `12px + 56px = 68px`
- Expanded: `12px + 224px = 236px`

**Files:**
- Modify: `resources/views/layouts/partials/settings-flyout.blade.php`
- Modify: `resources/views/layouts/partials/sidebar.blade.php` (profile flyout at bottom)

**Step 1: Update settings-flyout left offset**

In `settings-flyout.blade.php`, line 26, change:
```
:style="{ left: sidebarCollapsed ? '56px' : '224px', top: '12px', maxHeight: 'calc(100vh - 24px)' }"
```

To:
```
:style="{ left: sidebarCollapsed ? '68px' : '236px', top: '12px', maxHeight: 'calc(100vh - 24px)' }"
```

**Step 2: Update profile flyout left offset**

In `sidebar.blade.php`, the profile flyout div (after `</nav>`), change:
```
:style="{ left: sidebarCollapsed ? '56px' : '224px', bottom: '12px' }"
```

To:
```
:style="{ left: sidebarCollapsed ? '68px' : '236px', bottom: '12px' }"
```

**Step 3: Verify visually**

- Open Settings flyout: should appear flush against the right edge of the pill, no gap/overlap
- Open Profile flyout: same check
- Test both in collapsed and expanded sidebar states

**Step 4: Commit**

```bash
git add resources/views/layouts/partials/settings-flyout.blade.php resources/views/layouts/partials/sidebar.blade.php
git commit -m "fix: update flyout left offsets for pill sidebar position"
```

---

### Task 3: Update app.blade.php — body background, ml offsets, rounded corner

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

**Step 1: Darken body background**

Line 14, change:
```
bg-slate-50 dark:bg-slate-900
```
To:
```
bg-slate-200 dark:bg-slate-950
```

This creates contrast so the pill shadow and the rounded corner on the main content are visible.

**Step 2: Update main content wrapper ml offsets + add rounded corner**

Line 29, change:
```html
<div :class="sidebarCollapsed ? 'md:ml-14' : 'md:ml-56'" class="flex flex-col min-h-screen transition-all duration-300 ease-in-out">
```

To:
```html
<div :class="sidebarCollapsed ? 'md:ml-[4.25rem]' : 'md:ml-[14.75rem]'" class="flex flex-col min-h-screen bg-slate-50 dark:bg-slate-900 md:rounded-tl-2xl transition-all duration-300 ease-in-out">
```

Explanation:
- `md:ml-[4.25rem]` = 68px (collapsed pill right edge)
- `md:ml-[14.75rem]` = 236px (expanded pill right edge)
- `md:rounded-tl-2xl` = curved top-left corner matching the screenshot
- `bg-slate-50 dark:bg-slate-900` = restore content background on the inner div (body is now darker)

**Step 3: Verify visually**

- Page background (behind sidebar pill) should appear slate/gray
- Main content area should have a visible rounded top-left corner
- The corner curve should show the darker body background behind it
- Sidebar pill shadow should be visible
- Expanding/collapsing sidebar: main content slides smoothly, no gap or overlap

**Step 4: Run Pint**

```bash
vendor/bin/pint resources/views/layouts/app.blade.php --format agent
```

**Step 5: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "feat: darker body background, rounded-tl content area, updated ml offsets"
```
