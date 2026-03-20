# Sidebar Pill Design

**Date:** 2026-03-21
**Scope:** Visual redesign of sidebar — floating pill shape + toggle button + main content rounded corner

## Overview

Redesign the sidebar from a full-height flush panel into a floating pill card, update the toggle button to a purple filled badge, and add a rounded top-left corner to the main content area.

## Changes

### 1. Sidebar — Floating Pill (`sidebar.blade.php`)

- **Position:** `fixed left-3 top-3 bottom-3` (instead of `fixed left-0 top-0 h-screen`)
- **Shape:** Add `rounded-2xl`
- **Background:** Change to `bg-white dark:bg-slate-800`
- **Shadow:** Add `shadow-md`
- **Remove:** `border-r` and `overflow-hidden` (use `overflow-y-auto` on inner scroll area only)
- **Height:** Driven by `top-3 bottom-3` (full height minus margins)

### 2. Toggle Button — Purple Filled Badge

- Replace the current chevron SVG button with a purple filled rounded badge
- Collapsed state: `>>` double-chevron icon
- Expanded state: `<<` double-chevron icon
- Style: `bg-violet-600 text-white rounded-xl p-1.5 hover:bg-violet-700`

### 3. Main Content — Rounded Top-Left Corner (`app.blade.php`)

- Add `rounded-tl-2xl` to the main content wrapper div
- Add `bg-white dark:bg-slate-900` to the main content wrapper to contrast with body background
- Update `ml` offsets to account for sidebar margin:
  - Collapsed: `md:ml-[4.5rem]` (sidebar left-3 + w-14 + small gap)
  - Expanded: `md:ml-[15rem]` (sidebar left-3 + w-56 + small gap)

### 4. Body Background

- Darken slightly so pill shadow and rounded corner are visible: `bg-slate-200 dark:bg-slate-950`

## Files to Modify

| File | Change |
|------|--------|
| `resources/views/layouts/partials/sidebar.blade.php` | Pill shape, new toggle button |
| `resources/views/layouts/app.blade.php` | ML offsets, rounded-tl-2xl, background |

## Non-Goals

- No changes to nav items, settings flyout, or profile flyout logic
- No mobile layout changes
- No dark mode specific overrides beyond existing pattern
