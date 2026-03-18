# Meeting Index Redesign — Design Document

**Date:** 2026-03-18
**Approach:** Blade + Alpine.js (Hybrid)
**Scope:** Full UX improvement of `meetings/index` (List + Calendar views)
**Controller/routes:** Unchanged — all filter logic stays in `MeetingSearchService`

---

## Goal

Apply the antaraFlow Design System to the Meeting Index page. Improve UX across all 6 areas: stat cards, filter drawer, meeting cards grid, calendar view, empty states, and responsive behaviour.

---

## Architecture

### File Changes

```
resources/views/meetings/
  ├── index.blade.php               ← full rewrite
  ├── partials/
  │   ├── _stat-cards.blade.php     ← new
  │   ├── _filter-drawer.blade.php  ← new
  │   ├── _meeting-card.blade.php   ← new
  │   ├── _meeting-table-row.blade.php ← new
  │   ├── _empty-state.blade.php    ← new
  │   └── _calendar.blade.php       ← full rewrite
```

**No changes to:**
- `MeetingController::index()`
- `MeetingSearchService`
- Routes (`meetings.index`)
- Model or policies

### Alpine.js Root State

```js
x-data="{
  view: '{{ request('view', 'list') }}',
  dense: false,
  filterOpen: false,
  activeFilters: {{ count(array_filter(request()->only(['search','status','project_id','date_from','date_to']))) }}
}"
```

URL params drive server state. Alpine drives UI state (drawer, view mode, dense toggle).

---

## Section 1: Page Header

- Left: Page title "Meetings" + MOM count badge
- Right: View toggle buttons (List / Calendar) with `aria-pressed`, Dense/Grid toggle icon, New MOM primary button
- View toggle submits hidden form to preserve URL params + sets `?view=list|calendar`

---

## Section 2: Stat Cards

4 clickable cards in `grid-cols-2 md:grid-cols-4`:

| Card | Color | Icon | URL |
|------|-------|------|-----|
| Total | Violet | Calendar | `?` (clear filter) |
| Draft | Slate | Pencil | `?status=draft` |
| Finalized | Green | Check circle | `?status=finalized` |
| Approved | Amber | Star | `?status=approved` |

- Active card (matching current `?status` param) gets violet ring
- Each card shows count from `$stats` array

---

## Section 3: Filter Drawer

Slide-in panel from right. Triggered by "Filter" button with active filter badge count.

**Fields:**
- Search text (text input with search icon)
- Project (select dropdown, populated from `$projects`)
- Status (select: All / Draft / In Progress / Finalized / Approved)
- Date From / Date To (date inputs)
- Tags (multi-select, future)

**Actions:**
- "Apply Filters" — submits form via GET to `meetings.index`
- "Clear All" — link to `route('meetings.index')` with no params

**Accessibility:**
- `role="dialog"`, `aria-modal="true"`, `aria-label="Filter meetings"`
- Focus trap when open
- `Esc` key closes drawer
- Backdrop click closes drawer (`@click.outside`)

**Responsive:**
- Mobile: full-width drawer
- Tablet+: 380px wide drawer

---

## Section 4: Meeting Cards Grid (List View)

Default view. Replaces table as primary display.

### Card anatomy:
```
┌─────────────────────────────────┐
│ [Status badge]          [•••]   │
│ MOM-2024-001                    │
│ Q1 Planning Session             │
│ 📅 17 Mar 2026 · 10:00–11:00am │
│ 📍 Board Room / Video call      │
│ ─────────────────────────────── │
│ [AY][MR][SL] +2    3 items →   │
└─────────────────────────────────┘
```

- Status badge colours match design system: Draft (slate), InProgress (blue + pulse dot), Finalized (green), Approved (amber)
- Attendee avatars: initials, stacked, max 3 + overflow "+N"
- Action items count: links to meeting detail
- Card click → `route('meetings.show', $meeting)`
- Three-dot menu: Edit, Duplicate, Delete (permission-gated)

### Dense (Table) view:
Toggle via icon button. Columns: MOM No. | Title | Project | Date | Status | Action Items | Actions. Same data, more compact.

### Grid:
`grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4`

---

## Section 5: Empty State

**With active filters:**
- Icon: magnifying glass
- Title: "No meetings match your filters"
- Body: "Try adjusting your search or clearing some filters."
- CTA: "Clear filters" button → `route('meetings.index')`

**No meetings at all:**
- Icon: calendar
- Title: "No meetings yet"
- Body: "Schedule your first meeting to get started with AI transcription."
- CTA: "Create Meeting" primary button (permission-gated)

---

## Section 6: Calendar View

Two-panel layout matching reference image.

### Left panel — Activity Calendar:

**Month tab strip:** Horizontal scrollable tabs (Jan–Dec). Active month highlighted violet. Click → `?month=YYYY-MM&view=calendar`.

**Activity grid:**
- Rows = weeks (1–8, labelled by week number)
- Columns = days (Mo–Su)
- Each cell: date number + event chips (colour-coded by meeting status)
- Weekend columns: subtle background tint
- "No events." text for empty days

**Event chip colours:**
| Status | Color |
|--------|-------|
| Draft | Slate `bg-slate-100 text-slate-600` |
| InProgress | Blue `bg-blue-100 text-blue-700` with pulse dot |
| Finalized | Green `bg-green-100 text-green-700` |
| Approved | Amber `bg-amber-100 text-amber-700` |
| Action item due | Rose `bg-rose-100 text-rose-700` |

### Right panel — w-72:

**Mini calendar:**
- Standard Mo–Su grid for current month
- Dot indicator on days with meetings
- Click day → `document.getElementById('week-{n}').scrollIntoView()`
- Today highlighted with violet circle

**Upcoming events list:**
- Heading: "Upcoming events" with Filter icon
- Up to 5 nearest upcoming meetings
- Each row: title, date+time, status badge, "X days left" chip
- Colour-coded left border by status
- "No upcoming events" empty state

---

## Section 7: Interactions Summary

| Action | Implementation |
|--------|---------------|
| View toggle | Alpine set `view`, submit hidden form |
| Filter drawer | `x-show` + `x-transition` (translateX) |
| Backdrop close | `@click` on overlay div |
| Esc close | `@keydown.escape.window` |
| Stat card filter | `<a href>` with `?status=` param |
| Dense/grid toggle | Alpine `dense` bool, `localStorage` persist |
| Month tab | `<a href>` with `?month=` param |
| Day click (mini cal) | JS `scrollIntoView()` on week row |
| Filter badge count | PHP `count(array_filter(request()->only([...])))` |

---

## Section 8: Responsive Behaviour

| Breakpoint | List View | Calendar View |
|------------|-----------|---------------|
| Mobile (<640px) | 1-col cards, full-width drawer | Single panel, horizontal scroll grid |
| Tablet (768px) | 2-col cards, 380px drawer | Single panel (right panel hidden) |
| Desktop (1024px+) | 3-col cards | Two-panel (left + right 288px) |

---

## Accessibility Checklist

- [ ] All interactive cards: `tabindex="0"`, `role="article"`, `Enter` to navigate
- [ ] Filter drawer: `role="dialog"`, focus trap, `Esc` close
- [ ] Status badges: text label (not colour-only)
- [ ] Stat cards: `aria-label` includes count + label
- [ ] View toggle: `aria-pressed` on active button
- [ ] Empty state: `role="status"`
- [ ] Attendee avatars: `aria-label="[Name]"` on initials div
- [ ] Action item count: descriptive text (not just number)
