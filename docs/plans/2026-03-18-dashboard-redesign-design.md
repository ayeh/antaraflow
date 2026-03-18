# Dashboard Redesign — Design Document

**Date:** 2026-03-18
**Approach:** Blade + Alpine.js (Hybrid)
**Scope:** Full UX improvement of `dashboard` — from generic stats page to a personal workspace
**Controller/routes:** `DashboardController` will be extended with new queries. Routes unchanged.

---

## Goal

Transform the Dashboard from a passive stats display into an **active personal workspace** — answering "what do I need to do right now?" for a user who is simultaneously a MOM writer, team member, and approver.

---

## Architecture

### File Changes

```
resources/views/
  └── dashboard.blade.php               ← full rewrite

app/Http/Controllers/
  └── DashboardController.php           ← extend with new queries
```

**No changes to:** routes, models, policies, AuthorizationService.

### New Controller Queries

```php
// This week's meetings
$thisWeekMeetings = MinutesOfMeeting::query()
    ->where('organization_id', $orgId)
    ->whereBetween('meeting_date', [now()->startOfWeek(), now()->endOfWeek()])
    ->with(['project'])
    ->orderBy('meeting_date')
    ->get();

// Pending approval count (finalized MOMs)
$pendingApproval = MinutesOfMeeting::query()
    ->where('organization_id', $orgId)
    ->where('status', MeetingStatus::Finalized)
    ->count();

// Recent org-wide MOM activity (audit logs)
$recentActivity = AuditLog::query()
    ->where('organization_id', $orgId)
    ->whereIn('auditable_type', [MinutesOfMeeting::class])
    ->with('user')
    ->latest()
    ->take(8)
    ->get();

// My overdue action items count (for stat card)
$myOverdueCount = ActionItem::query()
    ->where('organization_id', $orgId)
    ->where('assigned_to', $user->id)
    ->whereNotIn('status', [ActionItemStatus::Completed, ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])
    ->where('due_date', '<', now())
    ->count();
```

---

## Section 1: Stat Cards

5 personalized cards across full width. All clickable → redirect to filtered page.

| Card | Metric | Color | Link |
|---|---|---|---|
| My Action Items | Open + InProgress assigned to me | Violet | `/action-items?assigned_to=me` |
| Overdue | My action items past due date | Red | `/action-items?filter=overdue` |
| Meetings This Week | `meeting_date` in current week | Blue | `/meetings?view=calendar` |
| Pending Approval | MOMs with status `finalized` | Amber | `/meetings?status=finalized` |
| Completion Rate | My completed / total assigned % | Green | `/action-items` |

Design: same component pattern as meetings index `_stat-cards.blade.php` — icon in coloured square, large number, label, coloured ring on active state (none by default on dashboard).

---

## Section 2: Needs Attention Banner

**Conditional** — only renders when `$myOverdueCount > 0 OR $pendingApproval > 0`.

```
amber-50 bg / amber-200 border / amber-800 text (light mode)
amber-900/20 bg / amber-800 border / amber-300 text (dark mode)
```

Two rows max:
- Row 1: `🔴 N action items overdue` → links to `/action-items?filter=overdue`
- Row 2: `🟡 N MOMs pending approval` → links to `/meetings?status=finalized`

Each row: icon + count text + arrow link on right. Hidden completely if both counts are zero.

---

## Section 3: Main Content — Two Column Layout

### Left Column (`flex-1`)

#### This Week's Meetings
- Shows meetings with `meeting_date` in current Mon–Sun
- Empty state: "No meetings scheduled this week"
- Each row: coloured date badge (day + month) | title + time | location | status badge from `_status-badge` partial
- Clicking row → `route('meetings.show', $meeting)`

#### My Action Items
- Max 5 items, sorted by `due_date ASC`
- Each row: priority dot (red/orange/yellow/gray) | title | due date (red if past) | meeting context (small)
- Overdue items get `text-red-600` due date
- "View all action items →" link at bottom

### Right Column (`w-80 shrink-0`)

#### Recent MOM Activity
- Last 8 entries from `audit_logs` scoped to org, auditable_type = `MinutesOfMeeting`
- Each entry: user avatar (initials) | action description | time ago (`diffForHumans()`)
- Action descriptions: "created [MOM title]", "changed status to Finalized", etc.
- "View all MOMs →" link at bottom
- Empty state: "No recent activity"

---

## Layout Structure

```
┌─────────────────────────────────────────────────────────────┐
│  Page Header: "Dashboard" + [New MOM] button                │
├─────────────────────────────────────────────────────────────┤
│  Stat Cards (5 × flex, full width)                          │
├─────────────────────────────────────────────────────────────┤
│  ⚠️ Needs Attention banner (conditional)                    │
├──────────────────────────────────────┬──────────────────────┤
│  LEFT (flex-1)                       │  RIGHT (w-80)        │
│  ┌──────────────────────────────┐   │  ┌─────────────────┐ │
│  │ This Week's Meetings         │   │  │ Recent Activity  │ │
│  │ ...                          │   │  │ ...              │ │
│  └──────────────────────────────┘   │  └─────────────────┘ │
│  ┌──────────────────────────────┐   │                      │
│  │ My Action Items              │   │                      │
│  │ ...                          │   │                      │
│  └──────────────────────────────┘   │                      │
└──────────────────────────────────────┴──────────────────────┘
```

---

## Data Contract (view variables)

| Variable | Type | Source |
|---|---|---|
| `$stats` | array | existing + new keys |
| `$myOverdueCount` | int | new query |
| `$pendingApproval` | int | new query |
| `$thisWeekMeetings` | Collection | new query |
| `$upcomingActions` | Collection | existing (renamed context) |
| `$recentActivity` | Collection | new — audit_logs |
| `$canCreateMeeting` | bool | existing |

---

## Design System Tokens

Follows antaraFlow Design System (`2026-03-17`):
- Cards: `bg-white dark:bg-slate-800 rounded-2xl border border-gray-200 dark:border-slate-700`
- Section headers: `text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider`
- Stat cards: same pattern as `meetings/partials/_stat-cards.blade.php`
- Status badges: `@include('meetings.partials._status-badge', ['status' => $meeting->status])`
- Priority colours: red=Critical, orange=High, yellow=Medium, gray=Low
