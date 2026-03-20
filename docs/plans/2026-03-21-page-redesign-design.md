# Page Redesign — Projects, Analytics, Meetings Show

**Date:** 2026-03-21
**Approach:** Consistent System (Approach A) — header + stat cards + main content, matching meetings/index pattern
**Scope:** Visual/CSS only. No logic, Alpine.js, or backend changes.

## Design System Reference

Source: `resources/views/meetings/index.blade.php`

| Element | Classes |
|---------|---------|
| Page title | `text-2xl font-bold text-gray-900 dark:text-white` |
| Subtitle | `text-sm text-gray-500 dark:text-gray-400 mt-0.5` |
| Primary button | `bg-violet-600 hover:bg-violet-700 text-white px-4 py-2 rounded-xl text-sm font-medium` |
| Secondary button | `bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 px-4 py-2 rounded-xl text-sm` |
| Card | `bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700` |
| Stat card icon bg | `bg-violet-100 dark:bg-violet-900/30 rounded-xl p-3` |
| Table header | `bg-gray-50 dark:bg-slate-700/50 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider` |
| Row hover | `hover:bg-gray-50 dark:hover:bg-slate-700/30` |
| Status badge | `inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium` |
| Dark mode card | `dark:bg-slate-800 dark:border-slate-700` (NOT gray-800/gray-700) |

---

## Page 1: Projects Index (`resources/views/projects/index.blade.php`)

### Header
- Left: title "Projects" + subtitle "Manage your organization's projects"
- Right: "+ New Project" button (violet, rounded-xl)

### Stat Cards (4-col grid)
| Card | Icon | Color | Value |
|------|------|-------|-------|
| Total Projects | folder | violet | `$projects->count()` |
| Active | check-circle | green | `$projects->where('status', 'active')->count()` |
| Total Members | users | blue | `$projects->sum(fn($p) => $p->members->count())` |
| Total Meetings | calendar | purple | `$projects->sum(fn($p) => $p->meetings->count())` |

### Filter Bar
- Search input: `rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800`
- Status filter dropdown: same styling

### Table
- Wrapper: `bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700`
- Header: `bg-gray-50 dark:bg-slate-700/50`
- All `dark:bg-gray-*` → `dark:bg-slate-*`, `dark:border-gray-*` → `dark:border-slate-*`
- Buttons: `rounded-lg` → `rounded-xl`

### Empty State
- Card with icon, title, subtitle, violet CTA button — matching design system

---

## Page 2: Analytics Overview (`resources/views/analytics/index.blade.php`)

### Header
- Left: title "Analytics" + subtitle "Meeting performance overview"
- Right: date range selector buttons (Last 30 days / Last 90 days / Custom)

### Stat Cards (4-col grid)
| Card | Icon | Color | Value |
|------|------|-------|-------|
| Total Meetings | calendar | violet | from existing controller data |
| Completion Rate | check | green | from existing controller data |
| Action Items | list | blue | from existing controller data |
| Avg Duration | clock | orange | from existing controller data |

### Tab Navigation
- Replace `border-b-2 border-purple-500` active tab with pill button style
- Active: `bg-violet-600 text-white rounded-xl`
- Inactive: `text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-xl`
- Tabs wrapped in `flex gap-1 p-1 bg-gray-100 dark:bg-slate-800 rounded-xl` pill container

### Chart Cards
- `dark:bg-gray-800` → `dark:bg-slate-800`
- `border-gray-200` → consistent, add `dark:border-slate-700`
- Card header: `px-6 py-4 border-b border-gray-200 dark:border-slate-700`
- `border-purple-500` → `border-violet-500`
- `bg-purple-100 text-purple-700` → `bg-violet-100 text-violet-700`

---

## Page 3: Meetings Show (`resources/views/meetings/show.blade.php`)

### Header
- Back link: `← Back to Meetings` with icon
- Row 2: Meeting title (h1, bold) + status badge (redesigned: `rounded-full px-3 py-1 text-sm font-medium`)
- Row 3: MOM number (mono) + date + live presence avatars
- Action buttons: moved to dedicated action bar below header

### Stepper (full redesign)
Replace current stepper with numbered circles connected by lines:
```
①━━━━━②━━━━━③━━━━━④━━━━━⑤
Setup  Attend Content Review Approve
```
- Circle: `w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold`
- Completed: `bg-violet-600 text-white`
- Active: `bg-violet-600 text-white ring-4 ring-violet-100 dark:ring-violet-900/30`
- Future: `bg-gray-200 dark:bg-slate-700 text-gray-500 dark:text-slate-400`
- Connector line: `flex-1 h-0.5 bg-gray-200 dark:bg-slate-700` (violet for completed)
- Label below circle: `text-xs font-medium` matching circle state color

### Action Bar
- Right-aligned flex row below stepper
- Export dropdown: `rounded-xl` menu
- Status-conditional buttons (Live/Finalize/Approve/Revert): keep color coding, update to `rounded-xl`
- Follow-up Email: `rounded-xl`

### Content Card
- Wrapper: `bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700 shadow-sm`
- Step content renders inside card
- Prev/Next nav at bottom of card: `border-t border-gray-200 dark:border-slate-700 pt-4 mt-6`
- All `dark:bg-gray-*` → `dark:bg-slate-*`, all `rounded-lg` buttons → `rounded-xl`

---

## Non-Goals
- No changes to Alpine.js data or methods
- No new routes or controllers
- No backend logic changes
- No mobile layout changes
- No changes to step partial files (only show.blade.php wrapper)
