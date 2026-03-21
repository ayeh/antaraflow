# Phase 2 — Full Feature Design Document

**Date:** 2026-03-22
**Approach:** Parallel Foundation First — migrations/models first, then services + controllers, then UI + API
**Domains:** H (Collaboration), I (Export), J (Analytics), Settings, API

---

## Section 1: Database Foundation

### New Tables

**`mom_mentions`**
- `id`, `comment_id` (FK comments), `mentioned_user_id` (FK users)
- `organization_id`, `minutes_of_meeting_id` (FK)
- `is_read` (bool, default false), `notified_at` (timestamp, nullable)

**`mom_reactions`**
- `id`, `comment_id` (FK comments), `user_id` (FK users)
- `emoji` (string — e.g. '👍', '❤️', '😂', '😮', '😢', '🎉')
- Unique constraint: `(comment_id, user_id, emoji)`

**`mom_exports`**
- `id`, `minutes_of_meeting_id` (FK), `user_id` (FK)
- `format` (enum: pdf, docx, csv)
- `file_path` (nullable), `file_size` (nullable integer)
- `downloaded_at` (timestamp, nullable), timestamps

**`mom_email_distributions`**
- `id`, `minutes_of_meeting_id` (FK), `sent_by` (FK users)
- `recipients` (json — array of email strings)
- `subject` (string), `body_note` (text, nullable)
- `export_format` (enum: pdf, docx), `status` (enum: pending, sent, failed)
- `sent_at` (timestamp, nullable), `failed_at` (timestamp, nullable), `error_message` (text, nullable)

**`export_templates`**
- `id`, `organization_id` (FK)
- `name` (string), `description` (text, nullable)
- `header_html` (text, nullable), `footer_html` (text, nullable), `css_overrides` (text, nullable)
- `logo_path` (string, nullable), `primary_color` (string, nullable), `font_family` (string, nullable)
- `is_default` (bool, default false)
- Timestamps, soft deletes

**`analytics_daily_snapshots`**
- `id`, `organization_id` (FK), `snapshot_date` (date)
- `total_meetings` (integer), `total_action_items` (integer), `completed_action_items` (integer)
- `overdue_action_items` (integer), `total_attendees` (integer), `ai_usage_count` (integer)
- `avg_meeting_duration_minutes` (decimal, nullable)
- Unique constraint: `(organization_id, snapshot_date)`

**`analytics_events`**
- `id`, `organization_id` (FK), `user_id` (nullable FK)
- `event_type` (string — e.g. 'meeting.viewed', 'export.downloaded')
- `subject_type` (string, nullable), `subject_id` (unsignedBigInt, nullable) — morphs
- `properties` (json, nullable)
- `occurred_at` (timestamp)
- Index on `(organization_id, event_type, occurred_at)`

**`user_settings`**
- `id`, `user_id` (FK unique)
- `notification_preferences` (json, nullable)
- `timezone` (string, default 'UTC'), `locale` (string, default 'en')
- `two_factor_enabled` (bool, default false), `two_factor_secret` (encrypted string, nullable)
- Timestamps

---

## Section 2: Collaboration (H) — @mentions + Emoji Reactions

### @mentions

**Parsing:** `CommentService::addComment()` scans text for `@username` pattern. Lookup users in org. Creates `mom_mentions` records.

**Notification flow:**
1. `CommentAdded` event (existing) → dispatch `SendMentionNotificationsJob`
2. Job: check `user_settings.notification_preferences` → send in-app notification (Laravel `notifications` table) + email via `MentionedInCommentMail`
3. UI: notification bell in navbar with unread count badge

**In-text display:** `@Ahmad` rendered as `<span class="text-blue-600 font-medium">@Ahmad</span>`

**Files:**
- Extend: `CommentService` — add mention parsing to `addComment()`
- New: `SendMentionNotificationsJob`
- New: `MentionedInCommentMail` (Mailable)
- New: `NotificationController` — list + mark as read
- New: `UserNotification` model (uses Laravel notifications table)
- Extend: navbar layout partial — notification bell with unread count

### Emoji Reactions

**Flow:** Toggle reaction via PATCH endpoint. Add if not exists, remove if exists.

**Supported emojis:** 👍 ❤️ 😂 😮 😢 🎉 (6 preset)

**UI:** Row of emoji buttons below each comment with counts. Alpine.js handles toggle + count update in-place.

**Files:**
- New: `ReactionController` — toggle endpoint
- Extend: `resources/views/collaboration/comments.blade.php` — reaction bar + mention highlights

---

## Section 3: Export (I) — History, Email Distribution, Templates

### Export History

`ExportController` extended — every PDF/DOCX/CSV generation saves a `mom_exports` record. UI: "Export History" tab in meeting show page.

### Export Templates

`ExportTemplate` model per-organisation. `PdfExportService` checks org's default template → injects header/footer HTML + CSS overrides into DomPDF.

WYSIWYG-lite template editor in Settings page.

**Routes:**
- `GET/POST /settings/export-templates`
- `GET/PUT/DELETE /settings/export-templates/{template}`

### Email Distribution

User clicks "Email MOM" → modal with:
- Pre-filled recipients from attendees (editable)
- Subject (default: "Minutes of Meeting — {title}")
- Optional note
- Format selector (PDF/DOCX)

**Flow:** `EmailDistributionController` → create `mom_email_distributions` record (status: pending) → dispatch `SendMomEmailJob` → generate export, attach, send via Laravel Mail → update status to `sent`/`failed`.

**Files:**
- New: `EmailDistributionController`
- New: `SendMomEmailJob`
- New: `MomDistributionMail` (Mailable with attachment)
- New: `ExportTemplateController`
- New: `ExportTemplate` model
- Extend: `ExportController` — save history + apply template

---

## Section 4: Analytics (J) — Full Pipeline

### Daily Snapshot Job

`GenerateDailyAnalyticsSnapshotJob` — Laravel scheduler, runs nightly. Aggregates counts per org → upsert into `analytics_daily_snapshots`.

### Event Tracking

`AnalyticsEventService` — lightweight service:
```php
AnalyticsEventService::track('meeting.viewed', $meeting, $user);
AnalyticsEventService::track('export.downloaded', $meeting, $user, ['format' => 'pdf']);
```
Called from relevant controllers. Stored in `analytics_events`.

### Audit Log UI

`GET /audit-log` — table with date range, user, action type filters. Uses existing `AuditLog` model.

### Analytics Dashboard Update

Extend `AnalyticsService` — replace slow raw queries with reads from `analytics_daily_snapshots` for trend charts. Keep raw queries for real-time today's stats widgets.

**Files:**
- New: `GenerateDailyAnalyticsSnapshotJob` + scheduler registration in `routes/console.php`
- New: `AnalyticsEventService`
- New: `AuditLogController`
- New: `resources/views/audit-log/index.blade.php`
- Extend: `AnalyticsService`

---

## Section 5: Settings

### Profile Settings
`GET/PUT /settings/profile` — name, avatar upload, timezone, locale.

### Notification Preferences
`GET/PUT /settings/notifications` — per-event toggles in `user_settings.notification_preferences` JSON:
```json
{
  "mention_in_comment": { "email": true, "in_app": true },
  "action_item_assigned": { "email": true, "in_app": true },
  "meeting_finalized": { "email": false, "in_app": true },
  "action_item_overdue": { "email": true, "in_app": true }
}
```

### Security Settings
`GET /settings/security`:
- Password change (`PUT /settings/security/password`)
- Active sessions list
- 2FA setup (TOTP — check for existing package, else manual implementation)

### Integrations
`GET /settings/integrations` — status cards for Google Calendar (uses existing `calendar_connections` table) and Microsoft Teams webhook (uses `organizations.teams_webhook_url`).

### API Keys
`GET /settings/api-keys` — list keys (masked token, created_at, last_used_at).
`POST /settings/api-keys` — generate new key.
`DELETE /settings/api-keys/{key}` — revoke.

**New Controllers:** `ProfileSettingsController`, `NotificationSettingsController`, `SecuritySettingsController`, `IntegrationSettingsController`
**New Model:** `UserSettings`
**New Views:** `settings/profile.blade.php`, `notifications.blade.php`, `security.blade.php`, `integrations.blade.php`, `api-keys.blade.php`

---

## Section 6: API Full Coverage (/api/v1/*)

### New Endpoints

| Resource | Method | Endpoint |
|----------|--------|----------|
| Attendees | GET/POST | `/api/v1/meetings/{id}/attendees` |
| Attendees | PATCH/DELETE | `/api/v1/meetings/{id}/attendees/{id}` |
| Transcriptions | GET | `/api/v1/meetings/{id}/transcriptions` |
| Transcriptions | GET | `/api/v1/meetings/{id}/transcriptions/{id}` |
| Comments | GET/POST | `/api/v1/meetings/{id}/comments` |
| Comments | PUT/DELETE | `/api/v1/comments/{id}` |
| Exports | GET | `/api/v1/meetings/{id}/export/pdf` |
| Exports | GET | `/api/v1/meetings/{id}/export/docx` |
| Search | GET | `/api/v1/search?q=` |
| Analytics | GET | `/api/v1/analytics/summary` |
| Webhooks | GET/POST/DELETE | `/api/v1/webhooks` |
| API Info | GET | `/api/v1` |

### Rate Limiting

Custom limiter in `bootstrap/app.php`:
- Free tier: 60 req/min
- Pro tier: 300 req/min
- Based on org's `SubscriptionPlan` tier

### New Files

**Controllers:** `V1/AttendeeApiController`, `V1/TranscriptionApiController`, `V1/CommentApiController`, `V1/AnalyticsApiController`, `V1/WebhookApiController`, `V1/ApiInfoController`

**Resources:** `AttendeeResource`, `TranscriptionResource`, `CommentResource`, `AnalyticsSummaryResource`

---

## Implementation Order (Parallel Foundation First)

### Wave 1 — Migrations + Models
All 8 new migrations + models: `MomMention`, `MomReaction`, `MomExport`, `MomEmailDistribution`, `ExportTemplate`, `AnalyticsDailySnapshot`, `AnalyticsEvent`, `UserSettings`

### Wave 2 — Backend Services + Controllers
Parallel tracks:
- **H track:** `SendMentionNotificationsJob`, `ReactionController`, extend `CommentService`
- **I track:** `ExportTemplateController`, `EmailDistributionController`, `SendMomEmailJob`, extend `ExportController`
- **J track:** `GenerateDailyAnalyticsSnapshotJob`, `AnalyticsEventService`, `AuditLogController`, extend `AnalyticsService`
- **Settings track:** `ProfileSettingsController`, `NotificationSettingsController`, `SecuritySettingsController`, `IntegrationSettingsController`, `UserSettings` model
- **API track:** All V1 controllers + resources + rate limiting

### Wave 3 — UI Views + Routes
All views + route registrations for each domain.
