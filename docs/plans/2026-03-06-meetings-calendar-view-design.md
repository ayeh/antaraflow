# Meetings Calendar View — Design Doc

**Date:** 2026-03-06
**Status:** Approved

## Overview

Add a monthly grid calendar view inside the Meetings page, accessible via a tab toggle alongside the existing list view. Meetings are displayed as chips in the calendar grid. Clicking a chip opens a modal preview with basic meeting details and a link to the full meeting page.

## Requirements

- Monthly grid calendar view (7-column day grid)
- Tab toggle in `/meetings` page: **List** | **Calendar**
- Meeting chips coloured by status (consistent with existing badges)
- Month navigation (prev/next)
- Click chip → modal preview with: MOM No, Title, Project, Status, Time, "View Meeting" button
- No new npm dependencies — use Alpine.js (already installed)

## Architecture

### New Route

```
GET /meetings/calendar  →  MeetingController@calendarData  (JSON)
Query params: year (int), month (int)
```

Returns array of meetings for the given month:
```json
[
  {
    "id": 1,
    "mom_number": "MOM-001",
    "title": "Weekly Sync",
    "meeting_date": "2026-03-10T09:00:00",
    "start_time": "09:00",
    "end_time": "10:00",
    "status": "finalized",
    "project": { "name": "AntaraFlow", "code": "AF" },
    "url": "/meetings/1"
  }
]
```

### Controller

`MeetingController::calendarData()`:
- Accepts `year` + `month` query params (default: current month)
- Queries meetings where `meeting_date` is within that month for the current org
- Eager loads: `project`
- Returns JSON response

### Views

**`meetings/index.blade.php`** — add tab toggle in header area. Conditionally render list or calendar partial based on active tab (stored in URL param `?view=calendar`).

**`meetings/partials/calendar-view.blade.php`** — new Alpine.js component:
- Builds calendar grid for current month
- Fetches meetings via `fetch('/meetings/calendar?year=&month=')` on init and on month change
- Renders meeting chips per day cell
- Modal overlay for meeting preview

## Data Flow

```
User visits /meetings?view=calendar
  → Blade renders calendar partial
  → Alpine.js initialises, fetches /meetings/calendar?year=2026&month=3
  → Renders 7×6 grid, places meeting chips on correct days
  → User clicks chip → modal opens with meeting details
  → User clicks "View Meeting" → navigates to /meetings/{id}
  → User clicks "< >" → Alpine.js fetches new month data
```

## Styling

- Status chip colours: draft=blue, finalized=orange, approved=green (match existing badge classes)
- Grid: Tailwind CSS, consistent with app design system
- Modal: standard modal overlay pattern used elsewhere in the app

## Files Changed

| File | Action |
|------|--------|
| `app/Domain/Meeting/Controllers/MeetingController.php` | Add `calendarData()` method |
| `routes/web.php` | Add `GET /meetings/calendar` route |
| `resources/views/meetings/index.blade.php` | Add tab toggle, conditional calendar partial |
| `resources/views/meetings/partials/calendar-view.blade.php` | New Alpine.js calendar component |

## Out of Scope

- Drag-and-drop rescheduling
- Weekly/day views
- Creating meetings from calendar
- Syncing from external calendar into antaraFlow
