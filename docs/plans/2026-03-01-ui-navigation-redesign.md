# antaraFLOW — UI Navigation Redesign

**Date:** 2026-03-01
**Status:** Approved for Implementation
**Approach:** Big Bang Replace

---

## Overview

Replace the current traditional 264px sidebar with a modern Hybrid Navigation system inspired by the antara* design system (antaraPROJECT UI/UX spec). Full implementation includes Icon Rail, Flyout Panel, Command Palette, FAB, Mobile Bottom Nav, dark mode, and updated color system.

---

## Section 1: Architecture & File Structure

### Files to Create / Modify

```
resources/
├── css/
│   └── app.css                             ← Update: Tailwind @theme color system
├── js/
│   ├── app.js                              ← Update: register Alpine components
│   ├── navigation.js                       ← NEW: Icon Rail + Flyout logic
│   ├── command-palette.js                  ← NEW: ⌘K search + keyboard nav
│   └── theme.js                            ← NEW: light/dark/system toggle
└── views/
    ├── layouts/
    │   ├── app.blade.php                   ← Refactor: full app shell
    │   └── partials/
    │       ├── icon-rail.blade.php         ← NEW: replaces sidebar.blade.php
    │       ├── flyout-panel.blade.php      ← NEW: 240px slide panel
    │       ├── header.blade.php            ← Update: search + notif + theme toggle
    │       ├── command-palette.blade.php   ← NEW: ⌘K overlay
    │       ├── fab.blade.php               ← NEW: Floating Action Button
    │       └── mobile-bottom-nav.blade.php ← NEW: Mobile navigation
```

### Navigation Groups (antaraFLOW)

| # | Icon | Group | Routes |
|---|------|-------|--------|
| 1 | 🏠 | Home | dashboard |
| 2 | 📄 | Meetings | meetings.index, meetings.create |
| 3 | ☑️ | Tasks | action-items.dashboard |
| 4 | ✨ | AI | extractions, chat, transcriptions |
| 5 | 📊 | Analytics | placeholder (Phase 2) |
| 6 | ⚙️ | Settings | organizations.index, members, AI config |
| — | 👤 | Avatar | Profile + Logout (bottom of rail) |

### Layout Shell

```
Desktop (≥768px):
┌─────────────────────────────────────────┐
│ [48px Icon Rail] │ [Header]             │
│                  │ [Page Content]       │
│                  │              [FAB]   │
└─────────────────────────────────────────┘

Mobile (<768px):
┌──────────────────────────────┐
│ [Header: title + notif]      │
│ [Page Content]               │
│ [Bottom Nav: 5 icons]        │
└──────────────────────────────┘
```

---

## Section 2: Color System & Component Specs

### Color System (Tailwind v4 `@theme`)

```css
/* Primary — Violet (active states, CTA, AI highlights) */
--color-primary-500: #8B5CF6;

/* Secondary — Blue (links, info) */
--color-secondary-500: #3B82F6;

/* Accent — Teal (success, AI-generated badges) */
--color-accent-500: #2DD4BF;

/* Light mode */
--bg: #F8FAFC | --surface: #FFFFFF | --border: #E2E8F0

/* Dark mode */
--bg: #0F172A | --surface: #1E293B | --border: #334155
```

### Icon Rail

| Property | Value |
|----------|-------|
| Width | 48px |
| Height | 100vh - 24px (12px margin top/bottom) |
| Border-radius | 24px (pill shape) |
| Position | fixed left-3, z-50 |
| Shadow | `0 4px 6px -1px rgba(0,0,0,0.1)` |
| Active state | Left violet bar (4px) + bg tint |
| Hover state | scale(1.05) + bg highlight |
| Tooltip | Show after 500ms delay |

### Flyout Panel

| Property | Value |
|----------|-------|
| Width | 240px |
| Position | left-[68px] (48 + 20px gap) |
| Border-radius | 16px |
| Animation | Slide from left, 200ms ease-out |
| Items stagger | 50ms delay each |
| Close trigger | Click outside or Escape |

### Command Palette (⌘K)

| Property | Value |
|----------|-------|
| Width | 500px |
| Max-height | 70vh |
| Position | Center screen, 20vh from top |
| Backdrop | Semi-transparent blur |
| Animation | Scale + fade, 150ms |
| Sections | Quick Actions · Navigation · Recent Meetings |
| Keyboard | ↑↓ navigate, Enter select, Escape close |
| Data source | Preloaded via `@json` in blade (no AJAX) |

### FAB

| Property | Value |
|----------|-------|
| Size | 56px circle |
| Position | fixed bottom-6 right-6 |
| Background | Gradient violet → blue |
| Shadow | Violet glow |
| Expand | Rotate icon 45° + show mini buttons |
| Mini buttons | New Meeting, New Action Item |

### Mobile Bottom Nav

| Property | Value |
|----------|-------|
| Height | 64px + safe-area-inset-bottom |
| Items | Home, Meetings, Tasks, AI, More (5 max) |
| More → | Bottom sheet: Settings + Profile + Theme toggle |
| Active state | Filled icon + label |

### Dark Mode

- Toggle button in header: cycles ☀️ → 🌙 → 💻 (system)
- Implementation: `dark` class on `<html>` element
- Persistence: `localStorage.getItem('theme')`

---

## Section 3: Interactivity & Alpine.js Data Flow

### Global App State

Single `x-data` on `<body>` (or wrapping div):

```javascript
{
  activeFlyout: null,           // null | 'home' | 'meetings' | 'tasks' | 'ai' | 'analytics' | 'settings'
  commandPaletteOpen: false,
  commandQuery: '',
  commandSelectedIndex: 0,
  fabExpanded: false,
  theme: localStorage.getItem('theme') || 'system',
  bottomSheetOpen: false,

  init() {
    this.applyTheme(this.theme);
    window.addEventListener('keydown', (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        this.commandPaletteOpen = true;
      }
      if (e.key === 'Escape') {
        this.commandPaletteOpen = false;
        this.activeFlyout = null;
        this.fabExpanded = false;
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
    localStorage.setItem('theme', theme);
  },

  cycleTheme() {
    const themes = ['light', 'dark', 'system'];
    const next = themes[(themes.indexOf(this.theme) + 1) % 3];
    this.theme = next;
    this.applyTheme(next);
  }
}
```

### Interaction Flow

```
Icon Rail click → activeFlyout = group → flyout slides in
Click outside   → activeFlyout = null  → flyout slides out

⌘K              → commandPaletteOpen = true → focus input
Type query      → filter nav + recent meetings (client-side, preloaded)
Enter/click     → navigate → commandPaletteOpen = false

FAB click       → fabExpanded = !fabExpanded → mini buttons appear
Mini click      → navigate to create page

Theme button    → cycleTheme() → update <html>.dark class
```

### Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| ⌘K / Ctrl+K | Open Command Palette |
| Escape | Close active panel/palette |
| ↑↓ | Navigate command palette results |
| Enter | Select command palette item |

### Server Data for Command Palette

Recent meetings preloaded in layout via shared view data (ViewServiceProvider atau middleware):

```php
// AppServiceProvider or middleware
View::share('recentMeetings', MinutesOfMeeting::query()
    ->where('organization_id', auth()->user()?->current_organization_id)
    ->latest()
    ->limit(5)
    ->get(['id', 'title', 'meeting_date']));
```

```blade
<div x-data="commandPalette(@json($recentMeetings ?? []))">
```

---

## Implementation Notes

- Semua interactivity guna Alpine.js (sudah dalam stack, tiada tambahan dependency)
- Guna Tailwind v4 `@theme` untuk color tokens
- `sidebar.blade.php` akan di-delete, diganti `icon-rail.blade.php`
- Semua views yang ada (`meetings/`, `action-items/`, dll) tidak perlu diubah — hanya `layouts/app.blade.php` dan partials
- Pint akan dijalankan selepas semua PHP files diubah
