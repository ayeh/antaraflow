# Design: Complete All Unbuilt & Incomplete Features

**Date:** 2026-03-01
**Status:** Approved

## Overview

Build all remaining unimplemented features for antaraFlow: Analytics, Export, Collaboration, User Profile, Organization Settings, Meeting Templates, Meeting Series, and Enhanced Dashboard.

## New Packages Required

- `barryvdh/laravel-dompdf` - PDF generation
- `phpoffice/phpword` - Word document generation
- Chart.js via CDN - no npm install needed

---

## 1. Analytics Domain (Detailed Reports)

### Architecture
- `AnalyticsController` - single page with chart data endpoints
- `AnalyticsService` - query aggregations from existing models (no new models)

### Data Points
- Meeting stats: count per month (bar), status distribution (donut), avg duration trend (line)
- Action items: completion rate, overdue trend, per-user breakdown (bar)
- Participation: top attendees, attendance rate, RSVP response rate
- AI usage: extraction count, chat sessions, transcription hours

### UI
- Full-page analytics dashboard at `/analytics`
- Date range picker filter
- Per-user filter
- Chart.js charts rendered client-side
- Stats cards at top, charts below

### Routes
- `GET /analytics` - main analytics page
- `GET /analytics/data` - JSON endpoint for chart data (AJAX)

---

## 2. Export Domain (PDF + Word + CSV)

### Architecture
- `ExportController` - handles export requests
- `PdfExportService` - renders Blade template to PDF via DomPDF
- `WordExportService` - generates .docx via PhpWord
- `CsvExportService` - native PHP CSV for action items

### Exports
- **PDF**: Full meeting minutes (title, date, attendees, content, action items, decisions)
- **Word**: Same content as PDF in editable .docx format
- **CSV**: Action items table (title, assignee, status, priority, due date)

### UI
- Export dropdown button on meeting show page
- Three options: PDF, Word, CSV

### Routes
- `GET /meetings/{meeting}/export/pdf`
- `GET /meetings/{meeting}/export/word`
- `GET /meetings/{meeting}/export/csv`

---

## 3. Collaboration Domain (Sharing + Commenting)

### New Models & Migrations

**MeetingShare**
- `id`, `meeting_id`, `shared_with_user_id` (nullable), `shared_by_user_id`, `permission` (enum: view/edit/comment), `share_token` (for guest links), `expires_at`, timestamps, soft deletes
- Belongs to meeting, shared_with user, shared_by user

**Comment** (polymorphic)
- `id`, `commentable_type`, `commentable_id`, `user_id`, `body` (text), `parent_id` (nullable, for replies), `organization_id`, timestamps, soft deletes
- Morphable to: MinutesOfMeeting, ActionItem

### Controllers
- `ShareController` - CRUD shares, generate/revoke share links
- `CommentController` - CRUD comments with replies

### UI
- Share panel in meeting show page (new tab or modal)
- Comment section at bottom of meeting content tab and action item show
- Reply threading (1 level deep)

### Routes
- `POST /meetings/{meeting}/shares` - create share
- `DELETE /meetings/{meeting}/shares/{share}` - revoke
- `POST /meetings/{meeting}/comments` - add comment
- `PUT /comments/{comment}` - edit
- `DELETE /comments/{comment}` - delete

---

## 4. User Profile & Settings

### Architecture
- `ProfileController` in Account domain
- `UpdateProfileRequest`, `UpdatePasswordRequest` form requests

### Sections
- **Basic info**: name, email, avatar upload
- **Password**: current password + new password + confirm
- **Preferences**: timezone, language, theme (light/dark/system), default meeting duration
- **Notifications**: toggles per notification type (meeting invite, action item assigned, meeting finalized, etc.)

### Storage
- Add `preferences` JSON column to users table (migration)
- Add `avatar_path` column to users table
- Avatar stored via Laravel filesystem (local/s3)

### Routes
- `GET /profile` - edit profile page
- `PUT /profile` - update profile
- `PUT /profile/password` - update password
- `POST /profile/avatar` - upload avatar

---

## 5. Organization Settings (Complete)

### Enhance Existing
- Add proper form fields: org name, description, logo upload
- Member management section: list members with roles, remove members
- Subscription info display (read-only)
- AI provider config display

### Storage
- Add `logo_path`, `description` columns to organizations table if missing
- Use existing settings JSON for other preferences

### Routes
- Keep existing `organizations.settings.edit/update`

---

## 6. Meeting Templates (CRUD UI)

### Architecture
- `MeetingTemplateController` in Meeting domain
- `CreateMeetingTemplateRequest`, `UpdateMeetingTemplateRequest`
- `MeetingTemplateService`

### Features
- CRUD: list, create, edit, delete templates
- Template fields: title, default agenda, default duration, default attendee groups
- "Use Template" button on meeting create form (pre-fills fields)

### Routes
- `resource('meeting-templates', MeetingTemplateController::class)`

---

## 7. Meeting Series (CRUD + Auto-generation)

### Architecture
- `MeetingSeriesController` in Meeting domain
- `MeetingSeriesService` with `generateUpcoming()` method
- `CreateMeetingSeriesRequest`, `UpdateMeetingSeriesRequest`

### Features
- CRUD: list, create, edit, delete series
- Recurrence: weekly, biweekly, monthly
- Auto-generate next N meetings based on recurrence
- Series detail view showing all child meetings

### Routes
- `resource('meeting-series', MeetingSeriesController::class)`
- `POST /meeting-series/{series}/generate` - generate upcoming meetings

---

## 8. Enhanced Dashboard

### Enhance Existing
- More stat cards: meetings this week, completion rate %, upcoming meetings count
- Mini sparkline charts (trend this week vs last week)
- Activity feed: recent changes across organization
- Quick links to analytics page
- Upcoming meetings list (next 7 days)

### Data
- All computed in `DashboardController` using existing models
- No new models needed
