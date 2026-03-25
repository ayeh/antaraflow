# antaraFlow Starter Kit — Design Document

> **Date:** 2026-03-25
> **Purpose:** Extract antaraFlow's design system, navigation, and branding into a reusable starter kit for other systems.

---

## Overview

A standalone HTML + Tailwind CSS + Alpine.js starter kit extracted from antaraFlow. Neutral/generic branding with swap-ready CSS variables. Targets developers who want to bootstrap a new system using antaraFlow's proven UI patterns without any backend dependency.

**Output location:** `/starter-kit/` inside the antaraFlow repo.

---

## Folder Structure

```
/starter-kit/
│
├── README.md
│
├── docs/
│   ├── DESIGN-SYSTEM.md
│   ├── COMPONENT-GUIDE.md
│   └── BRANDING-GUIDE.md
│
├── html/
│   ├── presets/
│   │   ├── preset-blank.css
│   │   └── preset-antaranote.css
│   │
│   ├── _sidebar.html
│   ├── _header.html
│   │
│   ├── login.html
│   ├── dashboard.html
│   ├── list.html
│   ├── show.html
│   └── form.html
│
└── assets/
    ├── app.css
    └── app.js
```

---

## Branding System

### Mechanism
CSS custom properties drive all brand colors. One file swap = full rebrand.

### `preset-blank.css`
```css
:root {
  --brand-primary:        #7c3aed;
  --brand-primary-dark:   color-mix(in srgb, var(--brand-primary) 85%, black);
  --brand-primary-light:  color-mix(in srgb, var(--brand-primary) 10%, white);
  --brand-sidebar-bg:     var(--brand-primary);
  --brand-sidebar-text:   #ffffff;
  --brand-font-heading:   'Inter', sans-serif;
  --brand-font-body:      'Inter', sans-serif;
  --brand-radius:         0.75rem;
}
```

### `preset-antaranote.css`
```css
:root {
  --brand-primary:        #0D7377;
  --brand-primary-dark:   #095153;
  --brand-primary-light:  #E6F4F4;
  --brand-sidebar-bg:     #095153;
  --brand-sidebar-text:   #ffffff;
  --brand-font-heading:   'Plus Jakarta Sans', sans-serif;
  --brand-font-body:      'Inter', sans-serif;
  --brand-radius:         0.75rem;
}
```

### Color Generation
All shade variants (50–900) are generated from `--brand-primary` using `color-mix()`:
```css
--brand-p50:  color-mix(in srgb, var(--brand-primary)  5%, white);
--brand-p100: color-mix(in srgb, var(--brand-primary) 10%, white);
--brand-p600: var(--brand-primary);
--brand-p700: color-mix(in srgb, var(--brand-primary) 85%, black);
```

---

## Design Tokens

### Colors
- **Neutral scale:** `slate-*` exclusively (never `gray-*`)
- **Brand primary:** `var(--brand-primary)` (violet by default)
- **Dark mode backgrounds:** `slate-800`, `slate-900`, `slate-950`
- **Borders:** `slate-200` light / `slate-700` dark
- **Status:** green (success), red (danger), amber (warning), sky (info)

### Typography
- **Heading font:** `var(--brand-font-heading)` — Plus Jakarta Sans or Inter
- **Body font:** `var(--brand-font-body)` — Inter
- **Mono:** JetBrains Mono (for code/data)
- **Scale:** display(36px), h1(28px), h2(22px), h3(18px), body(14px), small(12px)

### Spacing & Shape
- **Border radius:** `rounded-xl` (0.75rem) for buttons, cards, inputs
- **Card padding:** `p-6`
- **Page padding:** `p-6`
- **Sidebar width:** 15.5rem expanded / 5rem collapsed

---

## Pages

### `login.html`
- Centered card on brand-colored or pattern background
- Logo placeholder area (swap SVG/image)
- Email + password inputs with validation state
- Primary CTA button
- Dark mode support

### `dashboard.html`
- Page header with welcome text + date
- 4-up stat card grid (icon + value + label + trend)
- Recent activity list with avatars and timestamps
- Empty state component example
- Quick action buttons

### `list.html`
- Page header with title + primary CTA ("New [Item]")
- Search input + filter trigger button
- Filter drawer (Alpine.js `x-show` with collapse transition)
- Data table with sortable columns, status badges, action dropdown
- Pagination controls
- Empty state when no results

### `show.html`
- Back link + page title + status badge
- Action buttons (edit, delete, secondary actions)
- 2-column layout: main content (2/3) + sidebar panel (1/3)
- Tab navigation (Alpine.js tab switching)
- Related items list in sidebar

### `form.html`
- Page header with title (Create / Edit mode)
- Form sections with section titles and dividers
- Input types: text, email, select, textarea, date, toggle/checkbox
- Validation error state (red border + error message)
- Cancel + Submit button pair (right-aligned)

---

## Partial Components

### `_sidebar.html`
- Brand logo + app name at top
- Collapsible (icon-only mode) with Alpine.js `sidebarCollapsed` state
- Nav items: icon + label, active state highlighted with brand-primary
- Settings flyout trigger at bottom
- User avatar + name + logout at very bottom

### `_header.html`
- Hamburger for mobile
- Breadcrumb trail
- Search bar (command palette trigger)
- Dark mode toggle (sun/moon icon)
- Notification bell with unread badge
- User avatar dropdown (profile, settings, logout)

---

## Assets

### `assets/app.css`
- Tailwind v4 `@import "tailwindcss"`
- `@theme {}` block with custom properties
- Brand variable overrides (`.bg-brand-*`, `.text-brand-*`)
- Dark mode class-based (`dark:`)

### `assets/app.js`
- Alpine.js CDN import
- `@alpinejs/collapse` plugin
- `appState` Alpine component: `sidebarCollapsed`, `darkMode`, `commandPaletteOpen`
- Dark mode persistence via `localStorage`

---

## Docs

### `DESIGN-SYSTEM.md`
Covers: color palette, dark mode rules, typography scale, spacing system, border radius rules, status colors, icon usage.

### `COMPONENT-GUIDE.md`
Copy-paste snippets for: primary button, secondary button, danger button, badge, card, stat card, table row, form input, form select, validation error, empty state, alert/flash.

### `BRANDING-GUIDE.md`
Step-by-step: (1) pick a preset, (2) change `--brand-primary`, (3) swap logo SVG, (4) change font import URL, (5) update app name in HTML.

### `README.md`
- What is this kit
- How to use (open HTML file in browser, or integrate into backend)
- File map
- How to customise branding
- Tech dependencies (Tailwind CDN, Alpine.js CDN)

---

## Tech Stack (HTML Kit)

- **CSS:** Tailwind CSS v4 via CDN (`@tailwindcss/cdn`)
- **JS:** Alpine.js v3 CDN + `@alpinejs/collapse`
- **Icons:** Heroicons (inline SVG, no dependency)
- **No build step required** — open directly in browser
- **Optional:** Wire up to any backend by replacing static content with template tags

---

## Rules Extracted from antaraFlow

| Rule | Value |
|------|-------|
| Dark mode | `dark:bg-slate-*` NEVER `dark:bg-gray-*` |
| Primary color | `var(--brand-primary)` / violet-600 equivalent |
| Button radius | `rounded-xl` always |
| Card pattern | `bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6` |
| Active nav | `bg-brand-primary text-white` |
| Sidebar bg | `var(--brand-sidebar-bg)` |
| Content area | `bg-slate-50 dark:bg-slate-900` with `md:[clip-path:inset(0_0_0_0_round_1rem_0_0_0)]` |
| Body bg | `bg-slate-200 dark:bg-slate-950` |
