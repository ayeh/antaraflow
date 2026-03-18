# Sidebar Redesign Design

**Date:** 2026-03-18
**Goal:** Replace the icon-only rail + flyout architecture with a proper collapsible sidebar showing icon + text labels, improving navigation usability and visual hierarchy.

---

## Problem

The current sidebar is an icon-only rail (48px) that requires hover or click to reveal navigation labels. This is unfriendly for non-power users who cannot identify icons without tooltips.

## Decision

Replace `icon-rail.blade.php` + `flyout-panel.blade.php` with a single `sidebar.blade.php` component. Settings sub-items retain a flyout panel due to the large number of items. Sidebar is collapsible with state persisted in `localStorage`.

---

## Architecture

**Files changed:**
- Remove: `resources/views/layouts/partials/icon-rail.blade.php`
- Remove: `resources/views/layouts/partials/flyout-panel.blade.php`
- Create: `resources/views/layouts/partials/sidebar.blade.php`
- Update: `resources/views/layouts/app.blade.php`

**Alpine state** managed within sidebar's own `x-data`. The `collapsed` boolean is synced to the existing `appState` store so `app.blade.php` can reactively adjust `ml-*` offset.

---

## Sidebar Structure

```
┌─────────────────────┐
│  aF  antaraFLOW     │  ← brand mark + app name (name hidden when collapsed)
│                   « │  ← toggle button (collapse/expand)
├─────────────────────┤
│  🏠  Dashboard      │
│  📅  Meetings       │
│  ✅  Action Items   │
│  📁  Projects       │
│  ✨  AI Tools       │  ← note only, no sub-items
│  📊  Analytics      │
├─────────────────────┤
│  ⚙️  Settings    →  │  ← flyout for sub-items
├─────────────────────┤
│  [BB] Big Boss      │  ← avatar + name/email (bottom)
└─────────────────────┘
```

---

## Sizing & Layout

| State | Width | Content offset |
|---|---|---|
| Expanded | `w-56` (224px) | `md:ml-56` |
| Collapsed | `w-14` (56px) | `md:ml-14` |

- Sidebar: `fixed left-0 top-0 h-screen` (full height, no gap/rounding unlike current rail)
- Transition: `transition-all duration-300 ease-in-out` on both sidebar and content offset

---

## Visual Design

**Background:** `bg-slate-100 dark:bg-slate-800/60`
- Slightly different from page background (`bg-slate-50 dark:bg-slate-900`)
- Separated by a right border: `border-r border-slate-200 dark:border-slate-700`

**Nav items:**
- Normal: `rounded-xl px-3 py-2.5`, `text-slate-600 dark:text-slate-400`
- Hover: `bg-white dark:bg-slate-700 shadow-sm`
- Active: `bg-white dark:bg-slate-700 shadow-sm text-violet-600 dark:text-violet-400` + 3px violet left border accent
- Collapsed: icon centered, CSS tooltip on hover (no delay)

**Brand area:**
- Expanded: `aF` logo mark + "antaraFLOW" text
- Collapsed: `aF` logo mark only
- Toggle button: `«` / `»` chevron, `text-slate-400 hover:text-slate-600`

**Avatar (bottom):**
- Expanded: avatar circle + name + email (truncated)
- Collapsed: avatar circle only, tooltip on hover

---

## Settings Flyout

- Settings nav item has a `→` chevron (hidden when collapsed)
- Click opens a flyout panel positioned to the right of sidebar
- Flyout `left` value: `56px` (collapsed) or `224px` (expanded), set via Alpine `:style`
- Contents unchanged from current `flyout-panel.blade.php` (settings section only)
- Profile/account moved into the sidebar bottom avatar section

---

## Alpine.js State

```js
// Within sidebar x-data
{
    collapsed: localStorage.getItem('sidebar_collapsed') === 'true',
    settingsFlyout: false,
    toggle() {
        this.collapsed = !this.collapsed;
        localStorage.setItem('sidebar_collapsed', this.collapsed);
        // sync to appState if needed
    }
}
```

`app.blade.php` main content div uses `:class` bound to sidebar's `collapsed` state:
```blade
<div :class="$store.sidebar.collapsed ? 'md:ml-14' : 'md:ml-56'"
     class="transition-all duration-300 ease-in-out ...">
```

Use Alpine `$store` (`Alpine.store('sidebar', {...})`) registered in `app.js` to share state between sidebar and layout.

---

## Mobile

No changes. Mobile bottom nav (`mobile-bottom-nav.blade.php`) is unchanged. Sidebar is `hidden md:flex`.

---

## Navigation Items

| Key | Label | Route | Notes |
|---|---|---|---|
| home | Dashboard | `dashboard` | |
| meetings | Meetings | `meetings.index` | |
| tasks | Action Items | `action-items.dashboard` | |
| projects | Projects | `projects.index` | |
| ai | AI Tools | — | Note only, no navigation |
| analytics | Analytics | `analytics.index` | |
| settings | Settings | — | Flyout only |
