# Improvement Sprint Design — 2026-03-02

## Overview

Five improvement areas for antaraFlow after all planned features are complete (329 tests passing).
Goal: production readiness, API completeness, UI polish, test depth.

---

## Item 1: Performance — N+1 Query Fixes

### Scope
Audit every controller that renders relational data in Blade views or returns JSON. Add eager loading where missing.

### Targets
- `MeetingController::index` — load `tags`, `series`, `createdBy` for the list view
- `ActionItemController::index` (per-meeting) — load `assignedTo`, `createdBy`
- `ActionItemDashboardController::index` — load `meeting`, `assignedTo`
- `DashboardController::index` — load recent meetings with `tags`, upcoming actions with `meeting`
- `AnalyticsController` — ensure aggregate queries don't loop with N+1
- `AuditLogController::index` — load `user`, `auditable`

### Approach
Use `->with([...])` in Eloquent queries. For existing `->load()` calls, consolidate into the initial query. Add missing DB indexes where heavy queries sort/filter without one.

### Success Criteria
- No Eloquent query count regression in key pages
- Existing tests still pass

---

## Item 2: REST API Expansion — Full CRUD

### New Endpoints

**Meetings:**
- `POST /api/v1/meetings` — create meeting (title required, optional: meeting_date, location, duration_minutes, content, summary)
- `PATCH /api/v1/meetings/{id}` — update meeting (all fields optional, org-scoped)
- `DELETE /api/v1/meetings/{id}` — soft-delete meeting (org-scoped)

**Action Items:**
- `POST /api/v1/action-items` — create action item (title + minutes_of_meeting_id required; meeting must belong to org)
- `PATCH /api/v1/action-items/{id}` — update (all fields optional, org-scoped)

### Validation
Separate `StoreApiMeetingRequest` and `UpdateApiMeetingRequest` form request classes.
`StoreApiActionItemRequest` and `UpdateApiActionItemRequest`.

### Response Conventions
- `POST` returns `201` with the created resource
- `PATCH` returns `200` with updated resource
- `DELETE` returns `204 No Content`
- Validation errors return `422` with `{ "message": "...", "errors": { ... } }`
- Unauthorized/wrong-org returns `403`

### Tests
One test file per controller. Cover: create success, validation failure, cross-org rejection, 404 for missing resource, update partial fields.

---

## Item 3: UI Polish

### Empty States
Every page that can show an empty list needs a clear empty state with a heading, description, and (where applicable) a CTA button. Pages:
- Meetings index (no meetings yet)
- Action items dashboard (no items)
- Tags index (no tags)
- Templates index (no templates)
- Series index (no series)
- Notifications index (all caught up)
- Audit log index (no entries)
- API keys index (no keys)
- AI provider config index (no providers)

### Dark Mode Audit
Scan all Blade views for hardcoded light colors (e.g., `text-gray-900` without `dark:text-white`, `bg-white` without `dark:bg-slate-800`). Fix inconsistencies.

### Mobile Responsiveness
- Tables that overflow horizontally: add `overflow-x-auto` wrapper
- Action buttons that stack poorly on mobile: use `flex-wrap` or responsive variants
- Meeting show tabs: ensure horizontal scroll on mobile

---

## Item 4: Quality — Test Coverage

### New Tests
- **Edge cases per domain:**
  - Meeting: cannot edit approved meeting returns error, finalize only from draft/in-progress
  - Action item: carry-forward creates child with same data
  - API key: expired key returns 401, inactive key returns 401
  - Share: expired share link returns 410
- **Unit tests for Services:**
  - `MeetingService` — test `finalize()`, `approve()`, `revertToDraft()` logic
  - `ActionItemService` — test `carryForward()` logic

### Target
~400+ tests (up from 329).

---

## Item 5: Deployment — cPanel/DirectAdmin

### Components

**`deploy.sh`** — bash script for SSH deployment:
```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

**`.github/workflows/deploy.yml`** — GitHub Actions workflow:
- Trigger: push to `main`
- Steps: checkout → SSH into server → run deploy.sh
- Uses `appleboy/ssh-action` with secrets: `SSH_HOST`, `SSH_USER`, `SSH_KEY`, `SSH_PORT`

**`.env.example`** update:
- Ensure all environment variables are documented with examples
- Add comments for AI provider keys, queue connection, etc.

**Cron job** (for cPanel):
```
* * * * * /usr/bin/php /home/username/public_html/artisan schedule:run >> /dev/null 2>&1
```

**`.htaccess` at domain root** — redirect to `/public` for cPanel subdomain setup.

### Notes
- No Docker (cPanel incompatible)
- Queue driver: `database` (no Redis needed for basic hosting)
- Storage: local disk (no S3 needed unless uploads are heavy)
