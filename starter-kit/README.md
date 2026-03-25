# antaraFlow Starter Kit

A standalone HTML + Tailwind CSS + Alpine.js UI kit extracted from [antaraFlow](https://github.com/your-org/antaraFlow). Use this as a starting point for any new web application — no build step required.

---

## What's Included

```
starter-kit/
├── README.md                        ← You are here
├── docs/
│   ├── DESIGN-SYSTEM.md             ← Design tokens, colors, typography, layout rules
│   ├── COMPONENT-GUIDE.md           ← Copy-paste component snippets
│   └── BRANDING-GUIDE.md            ← How to rebrand for your system
├── html/
│   ├── presets/
│   │   ├── preset-blank.css         ← Neutral brand preset (default)
│   │   └── preset-antaranote.css    ← antaraNote teal brand preset
│   ├── _sidebar.html                ← Sidebar nav partial (copy into pages)
│   ├── _header.html                 ← Header partial (copy into pages)
│   ├── login.html                   ← Auth / sign-in page
│   ├── dashboard.html               ← Dashboard with stat cards + activity feed
│   ├── list.html                    ← Index/list page with search, filters, table, pagination
│   ├── show.html                    ← Detail/show page with tabs and sidebar
│   └── form.html                    ← Create/edit form with all input types
└── assets/
    ├── app.css                      ← Brand variable overrides for Tailwind
    └── app.js                       ← Alpine.js app state (sidebar, theme, flyouts)
```

---

## Quick Start

**No build step required.** Open any HTML file directly in your browser:

```bash
open starter-kit/html/dashboard.html
```

Or serve with any static file server:

```bash
npx serve starter-kit/html
# or
python3 -m http.server 8000 --directory starter-kit/html
```

---

## How to Use in a Project

### 1. Copy the assets

Copy `assets/app.css`, `assets/app.js`, and `html/presets/` into your project.

### 2. Add to your HTML `<head>`

```html
<!-- Brand preset (choose one, or create your own) -->
<link rel="stylesheet" href="path/to/presets/preset-blank.css">

<!-- Tailwind v4 CDN -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Brand overrides -->
<link rel="stylesheet" href="path/to/assets/app.css">

<!-- Alpine.js (collapse plugin first) -->
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="path/to/assets/app.js"></script>
```

### 3. Set up the app shell

```html
<body class="h-full bg-slate-200 dark:bg-slate-950 text-slate-900 dark:text-slate-100"
      x-data="appState" x-init="init()">
    <!-- Copy _sidebar.html content here -->
    <!-- Content area -->
    <div :class="sidebarCollapsed ? 'md:ml-[5rem]' : 'md:ml-[15.5rem]'"
         class="flex flex-col min-h-screen bg-slate-50 dark:bg-slate-900
                md:mt-3 md:[clip-path:inset(0_0_0_0_round_1rem_0_0_0)]
                transition-all duration-300 ease-in-out">
        <!-- Copy _header.html content here -->
        <main class="flex-1 p-6">
            <!-- Your page content -->
        </main>
    </div>
</body>
```

### 4. Customise branding

See `docs/BRANDING-GUIDE.md` for full instructions. Short version:

1. Open `html/presets/preset-blank.css`
2. Change `--brand-primary: #7c3aed;` to your color
3. Change `--brand-font-heading` / `--brand-font-body` to your fonts
4. Replace `AB` initials and `AppName` in `_sidebar.html` with your values

---

## Tech Dependencies

| Dependency | Version | CDN |
|------------|---------|-----|
| Tailwind CSS | v4 | `https://cdn.tailwindcss.com` |
| Alpine.js | v3 | `https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js` |
| Alpine Collapse | v3 | `https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js` |
| Heroicons | inline SVG | No CDN — icons are embedded inline |

No Node.js, npm, or build tools required for basic use.

---

## Page Templates

| File | Description | Key Patterns |
|------|-------------|--------------|
| `login.html` | Full-page auth, no sidebar | Centered card, form inputs, error state |
| `dashboard.html` | Main dashboard | Stat cards, activity feed, quick actions |
| `list.html` | Data index page | Search, filter drawer, table, row actions, pagination |
| `show.html` | Detail view | Back link, status badge, tabs, 2-col layout |
| `form.html` | Create/edit form | Sectioned form, all input types, validation error, toggle |

---

## Design System

See `docs/DESIGN-SYSTEM.md` for the complete specification.

**Key rules:**
- Dark mode: `dark:bg-slate-*` (never `dark:bg-gray-*`)
- Primary color: `bg-violet-600 hover:bg-violet-700` (overridden by preset)
- Buttons/inputs/cards: `rounded-xl`
- Sidebar/flyouts: `rounded-2xl`
- Badges: `rounded-full`

---

## Component Reference

See `docs/COMPONENT-GUIDE.md` for copy-paste snippets for every UI component:
buttons, badges, cards, stat cards, tables, form inputs, toggle, empty state, alerts, tabs, page headers.

---

## Extracted From

This starter kit was extracted from **antaraFlow**, a Laravel 12 SaaS meeting management platform.
The design system was preserved; all Laravel/PHP backend code has been removed.
