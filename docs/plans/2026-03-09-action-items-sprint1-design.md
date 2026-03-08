# Action Items Sprint 1 — Design Doc

**Date:** 2026-03-09
**Scope:** Inline Status Toggle, Quick Complete Checkbox, Filter Bar
**Approach:** Alpine.js + Dedicated AJAX Endpoints (server-side filtering, hybrid URL state)

---

## Overview

Sprint 1 improves the Action Items UX across two views:
- `/action-items` — cross-meeting dashboard
- `/meetings/{meeting}/action-items` — per-meeting list

Three features are delivered:
1. **Quick Complete Checkbox** — one-click to mark an item complete
2. **Inline Status Toggle** — click badge → floating mini-panel with status options + optional comment
3. **Filter Bar** — server-side filtering by status, priority, assignee; state reflected in URL

---

## Feature 1: Quick Complete Checkbox

A checkbox on the left of each table row. Clicking it immediately POSTs a status change to `completed` via AJAX. Alpine.js updates the row inline without page reload.

- Uses the same PATCH endpoint as the inline status toggle
- Sends `{ status: 'completed' }` with no comment
- On success: row badge updates, checkbox shows checked state
- On failure: checkbox reverts, toast error shown

---

## Feature 2: Inline Status Toggle (Floating Mini-Panel)

Clicking any status badge opens a floating popover anchored to the badge. The popover contains:
- 5 status option buttons (Open, In Progress, Completed, Cancelled, Carried Forward)
- Collapsible "Add a note?" textarea (maps to `comment` field in `ActionItemHistory`)
- Save button with loading spinner
- Dismisses on click-outside or Escape key

Alpine.js manages popover open/close state and AJAX submission. On success, the badge color and label update inline. On failure, the badge reverts and a toast error is shown.

---

## Feature 3: Filter Bar

A filter bar appears above the action items table on the dashboard view only (`/action-items`). It contains:
- **Status** multi-select chips (Open, In Progress, Completed, Cancelled, Carried Forward)
- **Priority** multi-select chips (Low, Medium, High, Critical)
- **Assignee** dropdown (All / Me)
- **Clear All** link

**Behaviour:**
- Alpine.js tracks selected filter state reactively
- Applying filters submits a GET form, updating URL query params (`?status[]=open&priority[]=high&assignee=me`)
- Server reads params, filters data, returns view
- On page load, filter bar reads URL params to pre-populate selections (hybrid approach)

Filtering is **server-side only** — no client-side data juggling.

---

## New Files

| File | Purpose |
|------|---------|
| `app/Domain/ActionItem/Controllers/ActionItemStatusController.php` | PATCH endpoint for inline status change |
| `app/Domain/ActionItem/Requests/UpdateActionItemStatusRequest.php` | Validates status + optional comment |
| `resources/views/components/action-item-status-badge.blade.php` | Reusable Blade component for status badge + popover |
| `resources/views/components/action-items/filter-bar.blade.php` | Filter bar component for dashboard |

---

## Modified Files

| File | Change |
|------|--------|
| `app/Domain/ActionItem/Controllers/ActionItemDashboardController.php` | Accept filter query params, pass to service |
| `app/Domain/ActionItem/Services/ActionItemService.php` | Extend `getDashboard()` with `status[]`, `priority[]`, `assignee` filter params |
| `resources/views/action-items/dashboard.blade.php` | Add filter bar + Alpine inline status toggle + checkbox |
| `resources/views/action-items/index.blade.php` | Add Alpine inline status toggle + checkbox |
| `routes/web.php` | Add `PATCH /meetings/{meeting}/action-items/{actionItem}/status` route |

---

## API Endpoint

```
PATCH /meetings/{meeting}/action-items/{actionItem}/status
```

**Request:**
```json
{ "status": "in_progress", "comment": "Started working on this" }
```

**Response (200):**
```json
{
  "id": 1,
  "status": "in_progress",
  "status_label": "In Progress",
  "status_color_class": "bg-yellow-100 text-yellow-700",
  "completed_at": null
}
```

**Error (422):**
```json
{ "message": "The status is not valid.", "errors": { "status": [...] } }
```

---

## Data Flow

### Status Update
```
User click badge → Alpine opens popover
User selects status + optional comment → Alpine PATCH request
Server: authorize → validate → ActionItemService::changeStatus() → return JSON
Alpine: update badge color + label inline → dismiss popover → show toast
On failure: revert badge → show error toast
```

### Filter
```
User selects filter chips → Alpine tracks state reactively
User clicks Apply → form GET submit → URL params updated
Server: ActionItemDashboardController reads params → getDashboard(filters) → return view
Filter bar pre-populates from URL params on load
Clear All → redirect /action-items (no params)
```

---

## Error Handling

| Scenario | Behaviour |
|----------|-----------|
| AJAX network failure | Badge reverts, toast: "Failed to update. Try again." |
| 422 Validation error | Inline error below status options in popover |
| 403 Unauthorised | Redirect to login |
| Filter returns 0 results | Empty state shown with "No items match your filters" |

---

## Testing Plan

- Feature test: `ActionItemStatusController` — happy path, unauthorized user, invalid status value, comment recorded in history
- Feature test: `ActionItemDashboardController` — filter by status, filter by priority, filter by assignee, combined filters, no filters
- Unit test: `ActionItemService::getDashboard()` — verify filter params correctly scope query
