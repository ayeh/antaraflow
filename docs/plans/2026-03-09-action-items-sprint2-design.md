# Action Items Sprint 2 — Design Doc

**Date:** 2026-03-09
**Scope:** Bulk Actions, Slide-over Quick Edit, Kanban Board
**Approach:** JSON API + Alpine.js (Approach B)
**Priority order:** Bulk Actions → Slide-over → Kanban

---

## Overview

Sprint 2 adds three UX improvements to the Action Items feature, building on the Sprint 1 inline status toggle, quick-complete checkbox, and filter bar.

1. **Bulk Actions** — select multiple items, apply status/priority change or delete in one go
2. **Slide-over Quick Edit** — click a row title to open a right-side drawer with all edit fields + activity timeline, without leaving the page
3. **Kanban Board** — toggle between table and kanban view on both the dashboard and per-meeting pages; drag-and-drop to change status

All features use JSON endpoints + Alpine.js, consistent with Sprint 1.

---

## Feature 1: Bulk Actions

### UX Flow

- A **select checkbox** column is added as the leftmost column on both the dashboard table (`/action-items`) and the per-meeting index table (`/meetings/{id}/action-items`)
- The table header gets a **select-all** checkbox
- When ≥1 item is selected, a **floating action bar** appears at the bottom of the viewport containing:
  - Item count label (`3 items selected`)
  - **Status** dropdown — apply chosen status to all selected items
  - **Priority** dropdown — apply chosen priority to all selected items
  - **Delete** button (red) — triggers a confirmation modal before executing
  - **Deselect all** × button to dismiss
- Existing quick-complete checkboxes remain unchanged

### New Endpoint

```
POST /action-items/bulk
Body:  { "ids": [1, 2, 3], "action": "status"|"priority"|"delete", "value": "in_progress" }
Response 200: { "updated": 3 }
Response 422: validation errors
```

- Global endpoint (not scoped to a meeting) — validates all IDs belong to the authenticated user's organisation
- Authorization: loops `ActionItemPolicy::update` (or `delete`) per item; skips unauthorized items and returns count of actually updated items
- New controller: `ActionItemBulkController` with a single `__invoke` method
- New form request: `BulkActionItemRequest`

### Alpine.js

A top-level `x-data` wrapper on the `<table>` manages:
- `selected: []` — array of selected item IDs
- `get allSelected()` — computed from `selected.length === total`
- `toggleAll()` / `toggle(id)`
- `applyBulk(action, value)` — AJAX POST, clears selection on success
- `showConfirmDelete` — controls delete confirmation modal

---

## Feature 2: Slide-over Quick Edit

### UX Flow

- Row title is changed from a `<a href="…show">` link to a `@click="slideOver.open(meetingId, itemId)"` trigger
- A right-side drawer (`w-96`, full-height) slides in with a semi-transparent backdrop
- Drawer contents (top to bottom):
  1. Header: item title (editable inline) + "Open full page →" link to `show.blade.php`
  2. **Edit fields**: description (textarea), status (reuses `action-item-status-badge` component), priority (select), assignee (select, populated from JSON response), due date (date input)
  3. **Save / Cancel** buttons
  4. **Activity timeline**: identical to `show.blade.php` history section
- Saving triggers PATCH → updates the row in the table inline → closes drawer
- Status changes inside the drawer use the existing badge component, which dispatches `action-item-status-changed` window event to keep the table row in sync automatically

### Endpoints (extended, not new)

```
GET  /meetings/{meeting}/action-items/{item}
     Accept: application/json
     → { id, title, description, status, priority, due_date, assigned_to,
         users: [{id, name}], history: [{...}] }

PATCH /meetings/{meeting}/action-items/{item}
      Accept: application/json
      → { id, title, description, priority, due_date, assigned_to, status, ... }
```

Both extend `ActionItemController::show` and `::update` with `$request->wantsJson()` checks. No new controllers needed.

### Alpine.js

A `slideOver` Alpine component lives at the page level (outside the table):
- `open: false`, `loading: false`, `saving: false`
- `item: null` — fetched JSON
- `history: []`
- `users: []`
- `open(meetingId, itemId)` — fetches JSON, sets `item`, opens drawer
- `save()` — PATCH, updates row in table, closes drawer
- `close()` — resets state

The component is defined once per page. Each row title calls `$dispatch('open-slide-over', { meetingId, itemId })` and the component listens via `@open-slide-over.window`.

---

## Feature 3: Kanban Board

### UX Flow

- A **view toggle** (Table | Kanban icons) appears top-right on both:
  - `/action-items` dashboard
  - `/meetings/{id}/action-items` per-meeting index
- Selected view is persisted in `localStorage` and reflected in URL (`?view=kanban`)
- **Kanban layout**: 5 columns — Open, In Progress, Completed, Cancelled, Carried Forward
  - Each column header shows the status label + item count badge
  - Cards show: title (click → slide-over), priority badge, due date (red if overdue), assignee name
  - Dashboard cards additionally show meeting name
- **Drag-and-drop** via SortableJS (installed via NPM)
  - Dropping a card into a new column fires `PATCH /meetings/{meeting}/action-items/{item}/status` (existing endpoint)
  - Local `items` array is updated optimistically; on failure the card reverts
- The filter bar on the dashboard works in both views (server-side filtering applies to kanban too)

### No New Endpoints

The same `$actionItems` collection already passed to the view is reused. The Kanban Blade component receives the collection and groups items into columns. Status PATCH uses the existing endpoint.

### New Files

| File | Purpose |
|------|---------|
| `resources/views/components/action-items/kanban-board.blade.php` | Kanban board Blade component with Alpine.js + SortableJS |
| `resources/views/components/action-items/view-toggle.blade.php` | Table/Kanban toggle button pair |

### SortableJS

Added via NPM: `npm install sortablejs`. Imported in `resources/js/app.js` and exposed as `window.Sortable` for Alpine `x-init` usage.

---

## Modified Files Summary

| File | Change |
|------|--------|
| `app/Domain/ActionItem/Controllers/ActionItemController.php` | Add `wantsJson()` branches to `show` and `update` |
| `app/Domain/ActionItem/Controllers/ActionItemBulkController.php` | **New** — bulk status/priority/delete |
| `app/Domain/ActionItem/Requests/BulkActionItemRequest.php` | **New** — validates bulk payload |
| `resources/views/action-items/dashboard.blade.php` | Add select checkboxes, floating action bar, view toggle, kanban component |
| `resources/views/action-items/index.blade.php` | Add select checkboxes, floating action bar, view toggle, kanban component |
| `resources/views/components/action-item-status-badge.blade.php` | No changes needed |
| `resources/views/components/action-items/kanban-board.blade.php` | **New** |
| `resources/views/components/action-items/view-toggle.blade.php` | **New** |
| `resources/js/app.js` | Import and expose SortableJS |

---

## Testing Plan

- Feature test: `ActionItemBulkController` — bulk status, bulk priority, bulk delete, unauthorized IDs skipped, empty IDs rejected
- Feature test: `ActionItemController::show` JSON — returns item + history + users
- Feature test: `ActionItemController::update` JSON — returns updated item JSON
- No browser tests for drag-and-drop (Alpine/SortableJS interaction tested manually)
