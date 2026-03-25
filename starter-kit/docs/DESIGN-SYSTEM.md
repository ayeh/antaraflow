# antaraFlow Starter Kit — Design System

> Version: 1.0 | Extracted from antaraFlow

This document is the single source of truth for design tokens, patterns, and rules in this starter kit. If you're building with or extending this kit, follow these specifications exactly.

---

## Color Palette

### Neutrals (Slate Scale)

Always use `slate-*`. Never use `gray-*` for neutrals.

| Token | Hex | Usage |
|-------|-----|-------|
| `slate-50` | `#F8FAFC` | Content area background (light) |
| `slate-100` | `#F1F5F9` | Subtle fills |
| `slate-200` | `#E2E8F0` | Body background (light), borders |
| `slate-300` | `#CBD5E1` | Disabled states |
| `slate-400` | `#94A3B8` | Muted text, placeholder icons |
| `slate-500` | `#64748B` | Secondary text |
| `slate-600` | `#475569` | Body text (light mode) |
| `slate-700` | `#334155` | Dark mode card headers |
| `slate-800` | `#1E293B` | Dark mode cards, sidebar |
| `slate-900` | `#0F172A` | Dark mode content area |
| `slate-950` | `#020617` | Dark mode body background |

### Brand Color (Violet / Swappable)

The primary brand color is driven by the `--brand-primary` CSS variable. All `violet-*` Tailwind classes are overridden to use the brand color via `assets/app.css`.

**Default:** `#7c3aed` (violet)

| Class | Usage |
|-------|-------|
| `bg-violet-600` | Primary buttons, active indicators, avatars |
| `hover:bg-violet-700` | Button hover states |
| `bg-violet-50 dark:bg-violet-900/20` | Active nav background, light fills |
| `text-violet-600 dark:text-violet-400` | Active nav text, links, icons |
| `border-violet-500` | Focus rings, active borders |

### Status Colors

| Status | Background | Text | Border |
|--------|------------|------|--------|
| Success/Active | `bg-green-50 dark:bg-green-900/20` | `text-green-700 dark:text-green-400` | `border-green-200 dark:border-green-800` |
| Warning/Pending | `bg-amber-50 dark:bg-amber-900/20` | `text-amber-700 dark:text-amber-400` | `border-amber-200 dark:border-amber-800` |
| Danger/Error | `bg-red-50 dark:bg-red-900/20` | `text-red-600 dark:text-red-400` | `border-red-200 dark:border-red-800` |
| Info | `bg-sky-50 dark:bg-sky-900/20` | `text-sky-600 dark:text-sky-400` | `border-sky-200 dark:border-sky-800` |
| Neutral/Inactive | `bg-slate-100 dark:bg-slate-700` | `text-slate-600 dark:text-slate-400` | `border-slate-200 dark:border-slate-600` |

---

## Dark Mode

**Rule: Always use `dark:bg-slate-*`. Never use `dark:bg-gray-*`.**

| Element | Light | Dark |
|---------|-------|------|
| Body background | `bg-slate-200` | `dark:bg-slate-950` |
| Content area | `bg-slate-50` | `dark:bg-slate-900` |
| Cards / panels | `bg-white` | `dark:bg-slate-800` |
| Sidebar | white/90 | `dark:bg-slate-800/90` |
| Borders | `border-slate-200` | `dark:border-slate-700` |
| Body text | `text-slate-900` | `dark:text-slate-100` |
| Secondary text | `text-slate-500` | `dark:text-slate-400` |
| Muted text | `text-slate-400` | `dark:text-slate-500` |

Dark mode is toggled via the `dark` class on `<html>`. State is persisted in `localStorage` and managed by `appState.cycleTheme()` in `assets/app.js`.

---

## Typography

### Fonts

| Role | Font | Weights |
|------|------|---------|
| Headings | `var(--brand-font-heading)` — Inter (blank) / Plus Jakarta Sans (antaranote) | 600, 700 |
| Body | `var(--brand-font-body)` — Inter | 400, 500 |
| Monospace | JetBrains Mono (for code/data — import separately if needed) | 400 |

### Scale

| Token | Size | Usage |
|-------|------|-------|
| Display | 36px / `text-4xl` | Hero sections |
| h1 | 28px / `text-2xl font-bold` | Page titles |
| h2 | 22px / `text-xl font-semibold` | Section headings |
| h3 | 18px / `text-base font-semibold` | Card titles |
| Body | 14px / `text-sm` | Default body text |
| Small | 12px / `text-xs` | Captions, metadata, labels |
| Tiny | 10px / `text-[10px]` | Sub-labels, timestamps |

---

## Spacing

| Context | Value |
|---------|-------|
| Page padding | `p-6` |
| Card padding | `p-6` (large), `p-5` (medium), `p-4` (small) |
| Grid gap | `gap-4` (standard), `gap-6` (large) |
| Stack gap | `space-y-4` (standard), `space-y-6` (large) |
| Inline gap | `gap-2` (tight), `gap-3` (standard) |

---

## Border Radius

| Element | Radius |
|---------|--------|
| Buttons | `rounded-xl` (0.75rem) |
| Form inputs | `rounded-xl` |
| Cards | `rounded-xl` |
| Badges / pills | `rounded-full` |
| Sidebar / flyouts | `rounded-2xl` |
| Avatars | `rounded-full` |
| Icon containers | `rounded-xl` or `rounded-lg` |

---

## Layout System

### App Shell

```
┌─────────────────────────────────────────────┐
│  Sidebar (fixed, left)   │  Content area     │
│  w-56 expanded           │  ml-[15.5rem]     │
│  w-14 collapsed          │  ml-[5rem]        │
│  rounded-2xl             │  rounded-tl-2xl   │
│                          │  bg-slate-50      │
│                          │  (clip-path)      │
└─────────────────────────────────────────────┘
```

- Sidebar: `fixed left-3 top-3 bottom-3`, `w-56` / `w-14` (Alpine `sidebarCollapsed`)
- Content area: `md:ml-[15.5rem]` / `md:ml-[5rem]`, `bg-slate-50 dark:bg-slate-900`
- Clip path: `md:[clip-path:inset(0_0_0_0_round_1rem_0_0_0)]` — creates rounded top-left corner
- Header: `sticky top-0 z-30 h-16`
- Main: `flex-1 p-6`

### Sidebar States

- Expanded: `w-56` — shows icon + label + user name
- Collapsed: `w-14` — shows icon only, label hidden via `x-show="!sidebarCollapsed"`
- Toggle: `toggleSidebar()` in `appState`
- Persistence: `localStorage.sidebarCollapsed`

---

## Navigation Patterns

### Active Nav Item
```html
<a href="#" class="flex items-center gap-3 px-2.5 py-2.5 rounded-xl
                   bg-violet-50 dark:bg-violet-900/20 text-violet-600 dark:text-violet-400 font-medium">
  <span class="absolute left-0 top-2 bottom-2 w-0.5 rounded-r-full bg-violet-600"></span>
  <!-- icon + label -->
</a>
```

### Inactive Nav Item
```html
<a href="#" class="flex items-center gap-3 px-2.5 py-2.5 rounded-xl transition-all
                   text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700
                   hover:shadow-sm hover:text-slate-800 dark:hover:text-slate-100">
  <!-- icon + label -->
</a>
```

---

## Implementation Rules (Critical)

1. **NEVER use `dark:bg-gray-*`** — always `dark:bg-slate-*`
2. **NEVER use `bg-purple-*`** — always `bg-violet-*`
3. **All buttons use `rounded-xl`** — no `rounded-lg` or `rounded-md` for buttons
4. **Status badges always use `rounded-full`** with bg + text + border all specified for both light and dark
5. **Form inputs use `rounded-xl`** — consistent with buttons
6. **Card pattern is consistent:** `bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700`
7. **Primary CTA always:** `bg-violet-600 hover:bg-violet-700 text-white rounded-xl`
