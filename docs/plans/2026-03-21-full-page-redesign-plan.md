# Full Page-Level Redesign Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Apply a consistent design system across all 26 remaining user-facing pages to match the established antaraFLOW visual language.

**Architecture:** Pure Blade view edits — no controller or model changes needed. Each task targets a group of related pages, applies design tokens, verifies no old classes remain, and commits.

**Tech Stack:** Laravel 12 Blade, Tailwind CSS v4, Alpine.js

---

## Design System Quick Reference

Apply these rules to every file:

| Element | Classes |
|---------|---------|
| Card | `bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700` |
| Card inner border | `border-b border-gray-200 dark:border-slate-700` |
| Button primary | `bg-violet-600 hover:bg-violet-700 text-white rounded-xl px-4 py-2 text-sm font-medium` |
| Button secondary | `bg-white dark:bg-slate-800 border border-gray-300 dark:border-slate-700 rounded-xl text-sm font-medium` |
| Button danger | `bg-red-600 hover:bg-red-700 text-white rounded-xl` |
| Form input | `rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 dark:text-white` |
| Table head | `bg-gray-50 dark:bg-slate-700/50` |
| Table row hover | `hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors` |
| Page title | `text-2xl font-bold text-gray-900 dark:text-white` |
| Page subtitle | `text-sm text-gray-500 dark:text-gray-400 mt-0.5` |
| Section heading | `text-base font-semibold text-gray-900 dark:text-white` |
| Stat card number | `text-2xl font-bold text-gray-900 dark:text-white` |
| Icon bg | `bg-{color}-100 dark:bg-{color}-900/30 rounded-xl p-3` |
| **NEVER use** | `dark:bg-gray-*`, `dark:border-gray-*`, `dark:hover:bg-gray-*`, `bg-purple-*`, `text-purple-*` |

**Verification command (run after each task):**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|dark:hover:bg-gray-\|bg-purple-\|text-purple-" resources/views/<folder>/
```

**Test command:** `php artisan test --compact`

---

## 🔴 TIER 1 — Core User Journey

### Task 1: Dashboard (`/dashboard`)

**Files:**
- Verify: `resources/views/dashboard.blade.php`

**Status:** Already uses `dark:bg-slate-800`, correct dark mode, stat cards with icons. Uses `rounded-2xl` for cards (keep — more distinctive than `rounded-xl`). Minor fixes only.

**Step 1: Check for remaining issues**
```bash
grep -n "dark:bg-gray-\|dark:border-gray-\|bg-purple-\|rounded-lg.*>.*</button>" resources/views/dashboard.blade.php
```
Expected: no matches (dashboard is already clean)

**Step 2: Fix if any issues found**
If any `dark:bg-gray-*` found → replace with `dark:bg-slate-*`
If any `dark:border-gray-*` found → replace with `dark:border-slate-*`

**Step 3: Run tests**
```bash
php artisan test --compact
```
Expected: 810 passed, 4 pre-existing failures

**Step 4: Commit**
```bash
git add resources/views/dashboard.blade.php
git commit -m "style: verify dashboard design system compliance"
```

---

### Task 2: Meetings Index (`/meetings`)

**Files:**
- Modify: `resources/views/meetings/index.blade.php`
- Modify: `resources/views/meetings/partials/_filter-drawer.blade.php` (if exists)
- Modify: `resources/views/meetings/partials/_status-badge.blade.php` (if exists)

**Step 1: Read the file**
Read `resources/views/meetings/index.blade.php` — check for:
- Any `dark:bg-gray-*` → fix to `dark:bg-slate-*`
- Any `dark:border-gray-*` → fix to `dark:border-slate-*`
- Any buttons using `rounded-lg` → fix to `rounded-xl`
- Any `bg-purple-*` → fix to `bg-violet-*`

**Step 2: Read and check filter drawer**
Read `resources/views/meetings/partials/_filter-drawer.blade.php`:
- Apply same fixes
- Filter apply/reset buttons → `rounded-xl`
- Filter panel bg → `dark:bg-slate-800`

**Step 3: Apply fixes to both files**
Replace all non-compliant classes.

**Step 4: Verify**
```bash
grep -n "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/meetings/index.blade.php
grep -n "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/meetings/partials/
```
Expected: no matches

**Step 5: Run tests**
```bash
php artisan test --compact
```

**Step 6: Commit**
```bash
git add resources/views/meetings/
git commit -m "style: apply design system to meetings index and filter drawer"
```

---

### Task 3: Action Items Dashboard (`/action-items`)

**Files:**
- Find and read: look for `resources/views/action-items/` directory — find the dashboard/index view

**Step 1: Find the action items views**
```bash
ls resources/views/action-items/
```
Read all files found.

**Step 2: Apply design system**
For each file found:
- Page header with title + subtitle + action button
- Stat cards with icon backgrounds (if it has stats)
- Table with correct dark mode classes
- Buttons → `rounded-xl`
- Status badges → `rounded-full` with `bg-{color}-100 dark:bg-{color}-900/30`
- Any `dark:bg-gray-*` → `dark:bg-slate-*`

**Step 3: Verify**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/action-items/
```

**Step 4: Run tests**
```bash
php artisan test --compact
```

**Step 5: Commit**
```bash
git add resources/views/action-items/
git commit -m "style: apply design system to action items pages"
```

---

### Task 4: Projects Show (`/projects/{id}`)

**Files:**
- Modify: `resources/views/projects/show.blade.php`

**Step 1: Read the file**
Read `resources/views/projects/show.blade.php`. Check for:
- Project detail card → `rounded-xl`
- Members list → table pattern with `dark:bg-slate-800`
- Recent meetings list → card with correct borders
- Add/remove member buttons → `rounded-xl`
- Any `dark:bg-gray-*`, `dark:border-gray-*` → fix

**Step 2: Apply fixes**

**Step 3: Verify**
```bash
grep -n "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/projects/show.blade.php
```

**Step 4: Run tests + commit**
```bash
php artisan test --compact
git add resources/views/projects/show.blade.php
git commit -m "style: apply design system to projects show page"
```

---

### Task 5: Meeting Create (`/meetings/create`)

**Files:**
- Modify: `resources/views/meetings/create.blade.php`
- Modify: `resources/views/meetings/wizard/step-setup.blade.php`
- Modify: `resources/views/meetings/wizard/step-attendees.blade.php`
- Modify: `resources/views/meetings/wizard/step-inputs.blade.php`
- Modify: `resources/views/meetings/wizard/step-review.blade.php`
- Modify: `resources/views/meetings/wizard/step-finalize.blade.php` (if exists)

**Step 1: Read each file and identify issues**
For each wizard step:
- Form inputs → `rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700`
- Section cards within steps → `bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700`
- Labels → `text-sm font-medium text-gray-700 dark:text-gray-300`
- Buttons → `rounded-xl`
- Any `dark:bg-gray-*` → `dark:bg-slate-*`

**Step 2: Apply fixes to all step files**

**Step 3: Verify**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/meetings/wizard/
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/meetings/create.blade.php
```

**Step 4: Run tests + commit**
```bash
php artisan test --compact
git add resources/views/meetings/create.blade.php resources/views/meetings/wizard/
git commit -m "style: apply design system to meeting create wizard steps"
```

---

## 🟡 TIER 2 — Settings & Management

### Task 6: Profile Edit (`/profile`)

**Files:**
- Modify: `resources/views/profile/edit.blade.php`

**Step 1: Read and fix**
- Avatar section → card with `rounded-xl`
- Personal info form → form input pattern
- Password form → `rounded-xl` submit button
- Danger zone (delete account) → `border-red-200 dark:border-red-800 rounded-xl`
- Any `dark:bg-gray-*` → `dark:bg-slate-*`

**Step 2: Verify + commit**
```bash
grep -n "dark:bg-gray-\|dark:border-gray-" resources/views/profile/edit.blade.php
php artisan test --compact
git add resources/views/profile/edit.blade.php
git commit -m "style: apply design system to profile edit page"
```

---

### Task 7: Organizations (`/organizations`)

**Files:**
- Modify: `resources/views/organizations/index.blade.php`
- Modify: `resources/views/organizations/show.blade.php`
- Modify: `resources/views/organizations/members/index.blade.php`
- Modify: `resources/views/organizations/create.blade.php`
- Modify: `resources/views/organizations/edit.blade.php`

**Step 1: Read all 5 files and identify issues**

**Step 2: Apply to index.blade.php**
- Page header + "New Organization" button → `rounded-xl`
- Cards/table → design system
- Dark mode fixes

**Step 3: Apply to show.blade.php**
- Organization details card → `rounded-xl`
- Members table → table pattern
- Settings/danger section → correct borders

**Step 4: Apply to members/index.blade.php**
- Members table → table pattern
- Invite button → `rounded-xl`

**Step 5: Apply to create/edit forms**
- Form inputs → `rounded-lg dark:bg-slate-700 dark:border-slate-600`
- Submit button → `rounded-xl`
- Card wrapper → `rounded-xl dark:bg-slate-800`

**Step 6: Verify all**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/organizations/
```

**Step 7: Run tests + commit**
```bash
php artisan test --compact
git add resources/views/organizations/
git commit -m "style: apply design system to organizations pages"
```

---

### Task 8: Meeting Templates (`/meeting-templates`)

**Files:**
- Modify: `resources/views/meeting-templates/index.blade.php`
- Modify: `resources/views/meeting-templates/show.blade.php`
- Modify: `resources/views/meeting-templates/create.blade.php`
- Modify: `resources/views/meeting-templates/edit.blade.php`

**Step 1: Apply to index**
- Table + empty state → design system
- "New Template" button → `rounded-xl`

**Step 2: Apply to show**
- Template detail card
- Usage/preview section

**Step 3: Apply to create/edit forms**
- Form inputs → `rounded-lg dark:bg-slate-700`
- Submit button → `rounded-xl`

**Step 4: Verify + commit**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/meeting-templates/
php artisan test --compact
git add resources/views/meeting-templates/
git commit -m "style: apply design system to meeting templates pages"
```

---

### Task 9: Meeting Series (`/meeting-series`)

**Files:**
- Modify: `resources/views/meeting-series/index.blade.php`
- Modify: `resources/views/meeting-series/show.blade.php`
- Modify: `resources/views/meeting-series/create.blade.php`
- Modify: `resources/views/meeting-series/edit.blade.php`

**Step 1: Apply same pattern as Task 8**
- Index: table + empty state + "New Series" button → `rounded-xl`
- Show: series detail card + meetings list
- Create/Edit: form inputs → `rounded-lg dark:bg-slate-700`, submit → `rounded-xl`

**Step 2: Verify + commit**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/meeting-series/
php artisan test --compact
git add resources/views/meeting-series/
git commit -m "style: apply design system to meeting series pages"
```

---

### Task 10: Tags + Attendee Groups + AI Provider Configs

**Files:**
- Modify: `resources/views/tags/index.blade.php`
- Modify: `resources/views/attendee-groups/index.blade.php`
- Modify: `resources/views/attendee-groups/create.blade.php`
- Modify: `resources/views/attendee-groups/edit.blade.php`
- Modify: `resources/views/ai-provider-configs/index.blade.php`
- Modify: `resources/views/ai-provider-configs/create.blade.php`
- Modify: `resources/views/ai-provider-configs/edit.blade.php`

**Step 1: Read all files**

**Step 2: Apply to tags/index**
- Tag list → card or table
- Add tag form (inline?) → `rounded-xl` button
- Color swatches → keep as-is

**Step 3: Apply to attendee-groups**
- Index: table + empty state
- Create/Edit: form with `rounded-xl` submit

**Step 4: Apply to ai-provider-configs**
- Index: provider list (likely cards)
- Create/Edit: API key fields, model selection → `rounded-lg dark:bg-slate-700`
- Sensitive fields (API keys) → keep existing masking

**Step 5: Verify all**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/tags/ resources/views/attendee-groups/ resources/views/ai-provider-configs/
```

**Step 6: Run tests + commit**
```bash
php artisan test --compact
git add resources/views/tags/ resources/views/attendee-groups/ resources/views/ai-provider-configs/
git commit -m "style: apply design system to tags, attendee groups, AI provider configs"
```

---

### Task 11: API Keys + Subscription + Usage

**Files:**
- Modify: `resources/views/api-keys/index.blade.php`
- Modify: `resources/views/subscription/index.blade.php`
- Modify: `resources/views/usage/index.blade.php`

**Step 1: Apply to api-keys/index**
- Key list with masked values → table pattern
- "Generate Key" button → `rounded-xl`
- Copy/revoke actions → correct styling
- Any `dark:bg-gray-*` → `dark:bg-slate-*`

**Step 2: Apply to subscription/index**
- Plan cards → `rounded-xl border`
- Current plan highlight → violet accent
- Upgrade/manage buttons → `rounded-xl`

**Step 3: Apply to usage/index**
- Usage bars/meters → violet accent color
- Stat cards with usage numbers
- Any `dark:bg-gray-*` → fix

**Step 4: Verify + commit**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/api-keys/ resources/views/subscription/ resources/views/usage/
php artisan test --compact
git add resources/views/api-keys/ resources/views/subscription/ resources/views/usage/
git commit -m "style: apply design system to API keys, subscription, and usage pages"
```

---

### Task 12: Audit Log + Notifications

**Files:**
- Modify: `resources/views/audit-log/index.blade.php`
- Modify: `resources/views/notifications/index.blade.php`

**Step 1: Apply to audit-log/index**
- Log entries table → table pattern
- Filter bar → `rounded-xl` buttons, `rounded-lg` inputs
- Any `dark:bg-gray-*` → fix

**Step 2: Apply to notifications/index**
- Notification list → card pattern
- Read/unread states → violet for unread
- "Mark all read" button → `rounded-xl`

**Step 3: Verify + commit**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/audit-log/ resources/views/notifications/
php artisan test --compact
git add resources/views/audit-log/ resources/views/notifications/
git commit -m "style: apply design system to audit log and notifications"
```

---

### Task 13: Webhooks (`/webhooks`)

**Files:**
- Modify: `resources/views/webhooks/index.blade.php`
- Modify: `resources/views/webhooks/show.blade.php`
- Modify: `resources/views/webhooks/create.blade.php`
- Modify: `resources/views/webhooks/edit.blade.php`

**Step 1: Apply to index**
- Webhook list → table or card list
- Status badges (active/inactive) → `rounded-full bg-green-100/bg-gray-100`
- "New Webhook" → `rounded-xl`

**Step 2: Apply to show**
- Webhook detail card
- Event list
- Recent deliveries table

**Step 3: Apply to create/edit**
- URL field, event checkboxes → `rounded-lg dark:bg-slate-700`
- Submit → `rounded-xl`

**Step 4: Verify + commit**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/webhooks/
php artisan test --compact
git add resources/views/webhooks/
git commit -m "style: apply design system to webhooks pages"
```

---

### Task 14: Extraction Templates (`/extraction-templates`)

**Files:**
- Modify: `resources/views/extraction-templates/index.blade.php`
- Modify: `resources/views/extraction-templates/create.blade.php`
- Modify: `resources/views/extraction-templates/edit.blade.php`

**Step 1: Apply to index**
- Template list → table pattern
- "New Template" → `rounded-xl`

**Step 2: Apply to create/edit**
- Form fields (schema/JSON) → `rounded-lg dark:bg-slate-700`
- Submit → `rounded-xl`

**Step 3: Verify + commit**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/extraction-templates/
php artisan test --compact
git add resources/views/extraction-templates/
git commit -m "style: apply design system to extraction templates pages"
```

---

### Task 15: Calendar Connections (`/calendar/connections`)

**Files:**
- Modify: `resources/views/calendar/connections.blade.php`

**Step 1: Read and fix**
- Connected calendar cards → `rounded-xl border`
- Connect/disconnect buttons → `rounded-xl`
- Provider logos/icons → keep as-is

**Step 2: Verify + commit**
```bash
grep -n "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/calendar/connections.blade.php
php artisan test --compact
git add resources/views/calendar/connections.blade.php
git commit -m "style: apply design system to calendar connections page"
```

---

## 🟢 TIER 3 — AI & Collaboration Features

### Task 16: AI Chat + AI Extractions

**Files:**
- Modify: `resources/views/chat/index.blade.php`
- Modify: `resources/views/extractions/index.blade.php`

**Step 1: Apply to chat/index**
- Chat message bubbles → keep functional layout
- Input area → `rounded-xl` send button
- Message list → correct dark mode background
- Any `dark:bg-gray-*` → `dark:bg-slate-*`

**Step 2: Apply to extractions/index**
- Extraction results card → `rounded-xl dark:bg-slate-800`
- Action items extracted → list with correct styling
- "Run Extraction" → `rounded-xl bg-violet-600`

**Step 3: Verify + commit**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/chat/ resources/views/extractions/
php artisan test --compact
git add resources/views/chat/ resources/views/extractions/
git commit -m "style: apply design system to AI chat and extractions pages"
```

---

### Task 17: Transcriptions + Manual Notes

**Files:**
- Modify: `resources/views/transcriptions/show.blade.php`
- Modify: `resources/views/manual-notes/index.blade.php`
- Modify: `resources/views/manual-notes/show.blade.php`
- Modify: `resources/views/manual-notes/create.blade.php`
- Modify: `resources/views/manual-notes/edit.blade.php`

**Step 1: Apply to transcriptions/show**
- Transcript text container → `rounded-xl dark:bg-slate-800`
- Speaker labels → color-coded badges
- Timestamp display → `text-xs text-gray-500 dark:text-gray-400`

**Step 2: Apply to manual-notes**
- Notes list → table or card list
- Note content card → `rounded-xl`
- Create/edit forms → input pattern
- Submit → `rounded-xl`

**Step 3: Verify + commit**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/transcriptions/ resources/views/manual-notes/
php artisan test --compact
git add resources/views/transcriptions/ resources/views/manual-notes/
git commit -m "style: apply design system to transcriptions and manual notes"
```

---

### Task 18: Follow-up Email + Meeting Versions

**Files:**
- Modify: `resources/views/meetings/follow-up-email.blade.php`
- Modify: `resources/views/meetings/versions/index.blade.php`
- Modify: `resources/views/meetings/versions/show.blade.php`

**Step 1: Apply to follow-up-email**
- Email preview card → `rounded-xl dark:bg-slate-800`
- Send button → `rounded-xl bg-violet-600`
- Edit/regenerate → secondary button `rounded-xl`

**Step 2: Apply to versions/index**
- Version list → table pattern with date/author columns
- "Restore" button → `rounded-xl`

**Step 3: Apply to versions/show**
- Version diff display → card with correct dark mode
- Side-by-side or inline diff → keep functional, fix background colors

**Step 4: Verify + commit**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|bg-purple-" resources/views/meetings/follow-up-email.blade.php resources/views/meetings/versions/
php artisan test --compact
git add resources/views/meetings/follow-up-email.blade.php resources/views/meetings/versions/
git commit -m "style: apply design system to follow-up email and meeting versions"
```

---

## 🏁 FINAL: Full QA Pass

### Task 19: Final verification

**Step 1: Broad grep across all views**
```bash
grep -rn "dark:bg-gray-\|dark:border-gray-\|dark:hover:bg-gray-\|bg-purple-\|text-purple-" resources/views/ --include="*.blade.php" | grep -v "admin/"
```
Expected: no matches (or only intentional uses)

**Step 2: Run full test suite**
```bash
php artisan test --compact
```
Expected: 810 passed, 4 pre-existing failures in MeetingLifecycleTest

**Step 3: Check browser logs for new JS errors**
Use `mcp__laravel-boost__browser-logs` tool with 20 entries.
Confirm no new errors beyond pre-existing calendar view error.

**Step 4: Check last backend error**
Use `mcp__laravel-boost__last-error` tool.
Confirm no new errors beyond pre-existing 2026-03-18 dashboard error.

**Step 5: Final commit**
```bash
git add -A
git commit -m "style: complete full page-level redesign — all 26 pages aligned to design system"
```

---

## Execution Notes

- Tasks 1–5 (Tier 1) should be done sequentially — they are the highest impact
- Tasks 6–15 (Tier 2) can be batched using parallel subagents (2-3 at a time)
- Tasks 16–18 (Tier 3) sequential, lower risk
- Task 19 always last — never skip QA
- If a page uses JavaScript-driven UI (charts, modals), only fix CSS classes — don't touch JS logic
- Pint only needed if `.php` files were changed (none expected here — all Blade)
