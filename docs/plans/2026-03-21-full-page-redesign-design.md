# Full Page-Level Redesign — Design Document
**Date:** 2026-03-21
**Scope:** All 26 remaining user-facing pages (Tier 1–3)
**Approach:** Consistent design system applied across all pages

---

## Design System Reference

All pages must follow these rules:

### Colors
- Background (page): `bg-slate-50 dark:bg-slate-900` (handled by layout)
- Cards/panels: `bg-white dark:bg-slate-800`
- Card borders: `border border-gray-200 dark:border-slate-700`
- Form inputs: `border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 dark:text-white`
- Dividers: `divide-gray-200 dark:divide-slate-700`
- Hover rows: `hover:bg-gray-50 dark:hover:bg-slate-700/30`
- Table head: `bg-gray-50 dark:bg-slate-700/50`
- **No** `dark:bg-gray-*` or `dark:border-gray-*` — always `slate`

### Accent Colors
- Primary action: `bg-violet-600 hover:bg-violet-700 text-white`
- Active/selected state: `bg-violet-50 dark:bg-violet-900/20 text-violet-600 dark:text-violet-400`
- Badges: colored `bg-{color}-100 dark:bg-{color}-900/30 text-{color}-700 dark:text-{color}-300`

### Border Radius
- Buttons: `rounded-xl`
- Cards: `rounded-xl`
- Form inputs: `rounded-lg` (slightly smaller, intentional)
- Badges/pills: `rounded-full`
- Icon backgrounds in stat cards: `rounded-xl`

### Typography
- Page title: `text-2xl font-bold text-gray-900 dark:text-white`
- Page subtitle: `text-sm text-gray-500 dark:text-gray-400 mt-0.5`
- Section heading: `text-base font-semibold text-gray-900 dark:text-white`
- Table header: `text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider`

### Stat Cards (where applicable)
```
bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5 flex items-center gap-4
  Icon background: bg-{color}-100 dark:bg-{color}-900/30 rounded-xl p-3
  Number: text-2xl font-bold text-gray-900 dark:text-white
  Label: text-xs text-gray-500 dark:text-gray-400 mt-0.5
```

### Page Header Pattern
```
<div class="flex flex-wrap items-start justify-between gap-4">
  <div>
    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{Title}</h1>
    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{Subtitle}</p>
  </div>
  <action-button class="...rounded-xl...">
</div>
```

### Empty State Pattern
```
<div class="bg-white dark:bg-slate-800 rounded-xl border ... px-6 py-16 text-center">
  <div class="mx-auto w-12 h-12 bg-gray-100 dark:bg-slate-700 rounded-xl flex items-center justify-center mb-4">
    <svg .../>
  </div>
  <h3 class="text-sm font-semibold text-gray-900 dark:text-white">...</h3>
  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">...</p>
  <CTA button if needed>
</div>
```

### Table Pattern
```
<div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead class="bg-gray-50 dark:bg-slate-700/50 border-b border-gray-200 dark:border-slate-700">
        <tr>
          <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
      <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors">
```

---

## Pages To Redesign

### 🔴 Tier 1 — Core User Journey (5 pages)

#### 1. Dashboard (`/dashboard`)
- **File:** `resources/views/dashboard.blade.php`
- **Changes:** Ensure consistent stat cards, dark mode classes, `rounded-xl` buttons
- **Note:** Pre-existing `total_meetings` key error in testing — check controller passes correct keys

#### 2. Meetings Index (`/meetings`)
- **File:** `resources/views/meetings/index.blade.php`
- **Changes:** This is the reference/benchmark page — standardise filter bar, stat pills, table/calendar view, buttons
- **Note:** Has filter drawer partial — include it in scope

#### 3. Action Items Dashboard (`/action-items`)
- **File:** `resources/views/action-items/` — find the dashboard view
- **Changes:** Stat cards, filters, table, dark mode

#### 4. Projects Show (`/projects/{id}`)
- **File:** `resources/views/projects/show.blade.php`
- **Changes:** Meeting list, member list, project details card

#### 5. Meeting Create (`/meetings/create`)
- **File:** `resources/views/meetings/create.blade.php`
- **Changes:** Wizard form container, dark mode, buttons

---

### 🟡 Tier 2 — Settings & Management (14 pages)

#### 6. Profile Edit (`/profile`)
- `resources/views/profile/edit.blade.php`
- Avatar upload, personal info form, password form

#### 7. Organizations Index + Show (`/organizations`)
- `resources/views/organizations/index.blade.php`
- `resources/views/organizations/show.blade.php`
- `resources/views/organizations/members/index.blade.php`

#### 8. Meeting Templates (`/meeting-templates`)
- `resources/views/meeting-templates/index.blade.php`
- `resources/views/meeting-templates/show.blade.php`
- `resources/views/meeting-templates/create.blade.php`
- `resources/views/meeting-templates/edit.blade.php`

#### 9. Meeting Series (`/meeting-series`)
- `resources/views/meeting-series/index.blade.php`
- `resources/views/meeting-series/show.blade.php`
- `resources/views/meeting-series/create.blade.php`
- `resources/views/meeting-series/edit.blade.php`

#### 10. Tags (`/tags`)
- `resources/views/tags/index.blade.php`

#### 11. Attendee Groups (`/attendee-groups`)
- `resources/views/attendee-groups/index.blade.php`
- `resources/views/attendee-groups/create.blade.php`
- `resources/views/attendee-groups/edit.blade.php`

#### 12. AI Provider Configs (`/ai-provider-configs`)
- `resources/views/ai-provider-configs/index.blade.php`
- `resources/views/ai-provider-configs/create.blade.php`
- `resources/views/ai-provider-configs/edit.blade.php`

#### 13. API Keys (`/api-keys`)
- `resources/views/api-keys/index.blade.php`

#### 14. Subscription (`/subscription`)
- `resources/views/subscription/index.blade.php`

#### 15. Usage (`/usage`)
- `resources/views/usage/index.blade.php`

#### 16. Audit Log (`/audit-log`)
- `resources/views/audit-log/index.blade.php`

#### 17. Webhooks (`/webhooks`)
- `resources/views/webhooks/index.blade.php`
- `resources/views/webhooks/show.blade.php`
- `resources/views/webhooks/create.blade.php`
- `resources/views/webhooks/edit.blade.php`

#### 18. Extraction Templates (`/extraction-templates`)
- `resources/views/extraction-templates/index.blade.php`
- `resources/views/extraction-templates/create.blade.php`
- `resources/views/extraction-templates/edit.blade.php`

#### 19. Calendar Connections (`/calendar/connections`)
- `resources/views/calendar/connections.blade.php`

---

### 🟢 Tier 3 — AI & Collaboration (7 pages)

#### 20. AI Chat (`/meetings/{id}/chat`)
- `resources/views/chat/index.blade.php`

#### 21. AI Extractions (`/meetings/{id}/extractions`)
- `resources/views/extractions/index.blade.php`

#### 22. Transcriptions Show (`/meetings/{id}/transcriptions/{id}`)
- `resources/views/transcriptions/show.blade.php`

#### 23. Manual Notes (index, show, create, edit)
- `resources/views/manual-notes/*.blade.php`

#### 24. Meeting Follow-up Email
- `resources/views/meetings/follow-up-email.blade.php`

#### 25. Meeting Versions
- `resources/views/meetings/versions/index.blade.php`
- `resources/views/meetings/versions/show.blade.php`

#### 26. Notifications
- `resources/views/notifications/index.blade.php`

---

## Out of Scope
- Admin panel (`/admin/*`) — has its own design system
- Email templates (non-UI)
- Guest/share views
- PDF exports
- Onboarding flows
- QR registration
- Error pages

---

## Implementation Strategy
- Use Subagent-Driven Development
- One subagent per page group (e.g. all Tier 2 Settings in parallel batches)
- Each subagent: read current file → apply design system → verify no `dark:bg-gray-*` remain → run pint
- After all pages: QA pass with browser logs + `php artisan test`
