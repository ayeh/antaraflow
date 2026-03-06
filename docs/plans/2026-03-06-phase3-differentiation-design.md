# Phase 3: Differentiation — Design Document

> **Date**: 6 March 2026
> **Scope**: 8 features (excluding 3.3 Multi-language AI and 3.5 Zapier)
> **Baseline**: 624 tests passing, 39 models, Phase 1 + Phase 2 complete

---

## Implementation Order

| Sprint | # | Feature | Complexity | Rationale |
|--------|---|---------|-----------|-----------|
| 1 | 3.2 | SSO (OAuth2 Social Login) | S | Standalone, enables enterprise adoption |
| 1 | 3.10 | AI meeting preparation | M | Pure AI service, no dependency |
| 1 | 3.4 | Meeting governance analytics | M | Extends existing analytics, read-only |
| 2 | 3.1 | Board meeting compliance | L | Largest — new models, voting, quorum |
| 2 | 3.7 | Advanced reporting | M | Builds on analytics from 3.4 |
| 3 | 3.6 | Real-time collaboration (Reverb) | L | Infrastructure change — broadcasting |
| 3 | 3.8 | White-label reseller | M | Extends branding, adds subdomain routing |
| 3 | 3.9 | Offline mode | M | Enhances PWA, adds IndexedDB + sync |

---

## Feature 3.2: SSO — OAuth2 Social Login

**Goal:** Allow users to sign in with Google, Microsoft, or GitHub via Laravel Socialite.

**New files:**
- `app/Domain/Account/Models/SocialAccount.php` — user_id, provider (string), provider_id (string), provider_email, avatar_url
- `database/migrations/xxxx_create_social_accounts_table.php`
- `database/factories/SocialAccountFactory.php`
- `app/Domain/Account/Controllers/SocialAuthController.php` — redirect(), callback(), unlink()
- `app/Domain/Account/Services/SocialAuthService.php` — findOrCreateUser(), linkAccount(), unlinkAccount()
- `resources/views/auth/partials/social-buttons.blade.php` — Google/Microsoft/GitHub buttons
- `resources/views/settings/connected-accounts.blade.php` — manage linked accounts
- `tests/Feature/Domain/Account/SocialAuthTest.php`

**Modify:**
- `config/services.php` — Add Google, Microsoft, GitHub OAuth credentials
- `resources/views/auth/login.blade.php` — Include social buttons partial
- `resources/views/auth/register.blade.php` — Include social buttons partial
- `routes/web.php` — Add `/auth/{provider}/redirect` and `/auth/{provider}/callback`
- `app/Domain/Account/Models/Organization.php` — Add `allowed_sso_providers` (JSON) setting
- Organization settings view — Add SSO provider toggles

**Flow:**
1. User clicks "Sign in with Google" → Socialite redirects to Google OAuth
2. Google callback → `SocialAuthService::findOrCreateUser()`
3. If email matches existing user → link social account, log in
4. If new email → create user + default org, log in, start onboarding
5. Per-org setting: admins can restrict which OAuth providers are allowed

---

## Feature 3.10: AI Meeting Preparation

**Goal:** AI suggests agenda items based on past meetings, open action items, and carried-forward items.

**New files:**
- `app/Domain/AI/Services/MeetingPreparationService.php` — gather context, call AI, parse response
- `app/Domain/AI/Controllers/MeetingPreparationController.php` — generate(), apply()
- `resources/views/meetings/partials/preparation-modal.blade.php` — suggested items UI
- `tests/Feature/Domain/AI/MeetingPreparationTest.php`

**Modify:**
- `app/Support/Enums/ExtractionType.php` — Add `MeetingPreparation` case
- Meeting show/create views — Add "AI Prepare Agenda" button
- `routes/web.php` — Add preparation routes

**Context gathering:**
- Last 3 meetings in same project (titles, summaries, decisions)
- Open action items assigned to attendees
- Carried-forward items from previous meetings
- Meeting type context (board meetings get governance-specific prompts)

**Response format (JSON):**
```json
{
  "suggested_agenda": ["Review Q1 budget", "Follow up on marketing proposal"],
  "carryover_items": ["Action: Update project timeline (overdue 3 days)"],
  "discussion_topics": ["Client feedback on demo"],
  "estimated_duration_minutes": 45
}
```

**Supports custom template** via ExtractionTemplate system (type: `meeting_preparation`).

---

## Feature 3.4: Meeting Governance Analytics

**Goal:** Governance-specific metrics: meeting costs, attendance trends, decision turnaround, compliance scores.

**New files:**
- `app/Domain/Analytics/Services/GovernanceAnalyticsService.php` — all governance metrics
- `app/Domain/Analytics/Controllers/GovernanceAnalyticsController.php` — data() JSON endpoint
- `resources/views/analytics/governance.blade.php` — governance dashboard tab
- `tests/Feature/Domain/Analytics/GovernanceAnalyticsTest.php`

**Modify:**
- `resources/views/analytics/index.blade.php` — Add "Governance" tab
- `routes/web.php` — Add governance analytics routes

**Metrics:**
1. **Meeting cost calculator** — duration × configurable hourly rate × attendee count
2. **Attendance rate trends** — present/total per month, by project
3. **Decision resolution time** — avg days from decision → related action item completed
4. **Action item completion trends** — on-time vs overdue per month
5. **Meeting type distribution** — pie chart of meeting types
6. **Approval turnaround** — avg days from finalized → approved
7. **Compliance score** — % meetings with: quorum met, minutes approved, all action items assigned

**Visualization:** Chart.js (already available via CDN). Date range filtering (existing pattern).

**CSV export:** Download governance metrics as CSV.

---

## Feature 3.1: Board Meeting Compliance Mode

**Goal:** Governance layer for board meetings — quorum tracking, voting on resolutions, formal resolution records.

**New files:**
- `app/Domain/Meeting/Models/BoardSetting.php` — organization_id, quorum_type (percentage/count), quorum_value, require_chair (bool), require_secretary (bool), voting_enabled (bool), chair_casting_vote (bool)
- `app/Domain/Meeting/Models/MeetingResolution.php` — meeting_id, resolution_number, title, description, mover_id (attendee), seconder_id (attendee), status (proposed/passed/failed/tabled/withdrawn)
- `app/Domain/Meeting/Models/ResolutionVote.php` — resolution_id, attendee_id, vote (for/against/abstain), voted_at
- `database/migrations/xxxx_create_board_settings_table.php`
- `database/migrations/xxxx_create_meeting_resolutions_table.php`
- `database/migrations/xxxx_create_resolution_votes_table.php`
- `database/factories/` for all 3 models
- `app/Support/Enums/ResolutionStatus.php` — Proposed, Passed, Failed, Tabled, Withdrawn
- `app/Support/Enums/VoteChoice.php` — For, Against, Abstain
- `app/Domain/Meeting/Services/QuorumService.php` — check(), calculate(), isQuorumMet()
- `app/Domain/Meeting/Services/ResolutionService.php` — CRUD, vote recording, tally calculation
- `app/Domain/Meeting/Controllers/BoardSettingController.php` — edit/update per org
- `app/Domain/Meeting/Controllers/ResolutionController.php` — CRUD within meeting
- `app/Domain/Meeting/Controllers/VoteController.php` — cast vote
- `app/Domain/Meeting/Requests/` — CreateResolutionRequest, UpdateResolutionRequest, CastVoteRequest, UpdateBoardSettingRequest
- `app/Domain/Meeting/Policies/ResolutionPolicy.php`
- `resources/views/settings/board-settings.blade.php`
- `resources/views/meetings/partials/board-compliance.blade.php` — quorum badge, resolutions section
- `resources/views/meetings/partials/resolution-card.blade.php` — individual resolution with voting
- `tests/Feature/Domain/Meeting/BoardComplianceTest.php`
- `tests/Feature/Domain/Meeting/ResolutionVoteTest.php`

**Modify:**
- `app/Domain/Meeting/Services/MeetingService.php` — Check quorum before allowing finalization (configurable)
- Meeting show view — Include board-compliance partial when meeting_type is BoardMeeting
- Organization settings nav — Add "Board Settings" link
- `routes/web.php` — Board settings routes, resolution routes, vote routes
- PDF/Word export — Include resolutions and vote tallies

**Quorum logic:**
- Type `percentage`: quorum_value% of invited attendees must be present
- Type `count`: at least quorum_value attendees must be present
- Block finalization if quorum not met (soft warning, configurable to hard block)

**Resolution numbering:** `RES-{YYYY}-{sequential per org}`

**Voting:**
- Only attendees marked as present can vote
- Each attendee gets one vote per resolution
- Auto-calculate: passed (majority for), failed (majority against or tie without chair casting)
- Chair's casting vote: if enabled and tied, chair's vote breaks tie

---

## Feature 3.7: Advanced Reporting

**Goal:** Scheduled and on-demand report generation with templates and email delivery.

**New files:**
- `app/Domain/Report/Models/ReportTemplate.php` — organization_id, name, type, filters (JSON), schedule (nullable cron), recipients (JSON), is_active, last_generated_at
- `app/Domain/Report/Models/GeneratedReport.php` — template_id, organization_id, file_path, file_size, generated_at, parameters (JSON)
- `database/migrations/xxxx_create_report_templates_table.php`
- `database/migrations/xxxx_create_generated_reports_table.php`
- `database/factories/` for both models
- `app/Support/Enums/ReportType.php` — MonthlySummary, ActionItemStatus, AttendanceReport, GovernanceCompliance
- `app/Domain/Report/Services/ReportGeneratorService.php` — orchestrates generation per type
- `app/Domain/Report/Generators/MonthlySummaryGenerator.php`
- `app/Domain/Report/Generators/ActionItemStatusGenerator.php`
- `app/Domain/Report/Generators/AttendanceReportGenerator.php`
- `app/Domain/Report/Generators/GovernanceComplianceGenerator.php`
- `app/Domain/Report/Jobs/GenerateReportJob.php` — async generation
- `app/Domain/Report/Mail/ReportReadyMail.php` — email with download link
- `app/Domain/Report/Controllers/ReportTemplateController.php` — CRUD + generate now
- `app/Domain/Report/Controllers/GeneratedReportController.php` — index + download
- `app/Domain/Report/Requests/CreateReportTemplateRequest.php`
- `app/Domain/Report/Requests/UpdateReportTemplateRequest.php`
- `app/Domain/Report/Policies/ReportTemplatePolicy.php`
- `resources/views/reports/templates/index.blade.php`, `create.blade.php`, `edit.blade.php`
- `resources/views/reports/generated/index.blade.php` — history with download links
- `resources/views/reports/pdf/` — PDF Blade templates per report type
- `app/Console/Commands/GenerateScheduledReportsCommand.php`
- `tests/Feature/Domain/Report/ReportTemplateTest.php`
- `tests/Feature/Domain/Report/ReportGenerationTest.php`

**Modify:**
- `routes/console.php` — Schedule GenerateScheduledReportsCommand
- `routes/web.php` — Report template and generated report routes
- Organization settings nav — Add "Reports" link

**Report types:**
1. **Monthly Meeting Summary** — meetings held, duration, attendees, action items created/completed
2. **Action Item Status** — all open/overdue items, by assignee, by priority
3. **Attendance Report** — attendance rates by person, by meeting type
4. **Governance Compliance** — quorum met %, resolutions passed, approval turnaround

**Scheduling:** Cron expression per template (e.g., `0 8 1 * *` = 1st of month at 8am). `GenerateScheduledReportsCommand` checks all active templates and dispatches jobs.

---

## Feature 3.6: Real-time Collaboration (Laravel Reverb)

**Goal:** Live updates for comments, meeting status, action items, and presence tracking.

**New files:**
- `config/broadcasting.php` — Reverb driver configuration
- `config/reverb.php` — Reverb server settings
- `app/Events/CommentAdded.php` — broadcastable
- `app/Events/MeetingStatusChanged.php` — broadcastable
- `app/Events/ActionItemUpdated.php` — broadcastable
- `app/Events/AttendeePresenceChanged.php` — broadcastable
- `resources/js/echo.js` — Echo initialization with Reverb
- `resources/js/meeting-live.js` — Alpine.js component for live meeting updates
- `tests/Feature/Broadcasting/MeetingBroadcastTest.php`

**Modify:**
- `composer.json` — Add `laravel/reverb` dependency
- `package.json` — Add `laravel-echo`, `pusher-js` dependencies
- `resources/js/app.js` — Import Echo setup
- `resources/js/bootstrap.js` — Configure Echo with Reverb
- `app/Domain/Collaboration/Services/CommentService.php` — Dispatch CommentAdded event
- `app/Domain/Meeting/Services/MeetingService.php` — Dispatch MeetingStatusChanged
- `app/Domain/ActionItem/Services/ActionItemService.php` — Dispatch ActionItemUpdated
- Meeting show view — Add live listeners, presence indicator, typing indicator
- `routes/channels.php` — Define private channel authorization

**Channels:**
- `meeting.{id}` (PrivateChannel) — comments, status, action items for specific meeting
- `organization.{id}` (PrivateChannel) — org-wide toast notifications
- `meeting.{id}.presence` (PresenceChannel) — who's viewing the meeting

**Frontend:**
- `meeting-live` Alpine.js component: listens to Echo channels, updates DOM reactively
- Live comment feed: new comments append without refresh
- Presence indicator: avatars of users currently viewing
- Toast notifications for status changes
- Typing indicator via whisper events (client-to-client, no server)

---

## Feature 3.8: White-label Reseller Program

**Goal:** Allow organizations to resell antaraFlow under their own brand with custom domains.

**New files:**
- `app/Domain/Account/Models/ResellerSetting.php` — organization_id, custom_domain, subdomain, is_reseller (bool), allowed_plans (JSON), commission_rate (decimal), max_sub_organizations
- `database/migrations/xxxx_create_reseller_settings_table.php`
- `database/factories/ResellerSettingFactory.php`
- `app/Domain/Account/Services/ResellerService.php` — manage sub-orgs, calculate commission, usage summary
- `app/Domain/Account/Controllers/ResellerController.php` — dashboard, sub-org management
- `app/Domain/Account/Requests/UpdateResellerSettingRequest.php`
- `app/Http/Middleware/ResolveSubdomain.php` — resolves subdomain to org, applies branding
- `resources/views/reseller/dashboard.blade.php` — sub-org list, usage stats, commission
- `resources/views/reseller/sub-organizations.blade.php` — manage sub-orgs
- `tests/Feature/Domain/Account/ResellerTest.php`

**Modify:**
- `app/Domain/Account/Models/Organization.php` — Add `parent_organization_id` (nullable FK), `is_reseller` accessor
- `database/migrations/xxxx_add_parent_org_to_organizations_table.php`
- `app/Domain/Account/Services/BrandingService.php` — Resolve org-level overrides (logo, colors, name, CSS)
- `bootstrap/app.php` — Register ResolveSubdomain middleware
- `routes/web.php` — Reseller management routes
- Admin panel — Manage resellers, toggle reseller status
- Organization settings — Branding override fields (logo, colors, app name, custom CSS)
- Login page — Show org branding when accessed via subdomain

**Subdomain routing:**
- `{subdomain}.antaraflow.test` → `ResolveSubdomain` middleware → find org with matching subdomain → apply branding
- Custom domain: CNAME to platform → middleware checks `custom_domain` field
- Org branding cascade: org overrides → reseller defaults → platform defaults

---

## Feature 3.9: Offline Mode (Read-only + Draft Queue)

**Goal:** View cached meeting data offline and queue notes/comments for sync when online.

**New files:**
- `resources/js/offline-store.js` — IndexedDB wrapper (meetings, notes, comments tables)
- `resources/js/offline-queue.js` — queue offline actions, sync on reconnect
- `resources/js/online-status.js` — Alpine.js component for online/offline detection
- `resources/views/components/offline-indicator.blade.php` — offline banner + sync status
- `resources/views/offline/meeting-viewer.blade.php` — read-only meeting from cache
- `tests/Feature/OfflineModeTest.php` — test API endpoints for sync

**Modify:**
- `public/sw.js` — Enhanced caching: pre-cache dashboard, cache meeting API responses, background sync registration
- `resources/js/app.js` — Import offline modules
- Meeting show view — Include offline indicator, cache meeting data on visit
- Meeting API responses — Add JSON endpoints for offline consumption (`/meetings/{id}/offline-data`)
- `routes/web.php` — offline data endpoint, sync endpoint

**IndexedDB schema:**
- `meetings` store: id, title, data (full JSON), cached_at
- `offline_actions` store: id, type (note/comment), meeting_id, payload, created_at, synced (bool)

**Caching strategy:**
- On meeting visit: cache full meeting data as JSON in IndexedDB
- Dashboard visit: cache recent 10 meetings list
- Max cache: 50 meetings, LRU eviction

**Sync flow:**
1. User goes offline → banner shows "You're offline"
2. User adds note/comment → queued in IndexedDB `offline_actions`
3. User comes back online → background sync triggers
4. `offline-queue.js` POSTs each queued action to server
5. On success: mark as synced, show toast "X items synced"
6. On conflict: server wins (last-write-wins), show notification

---

## Verification Strategy

After each feature:
1. `vendor/bin/pint --dirty --format agent`
2. `php artisan test --compact`
3. All existing 624+ tests must continue to pass

End-to-end:
- **SSO**: Login with Google/Microsoft, link/unlink accounts
- **AI Prep**: Generate agenda for project with past meetings, apply items
- **Governance Analytics**: View dashboard with date filtering, export CSV
- **Board Compliance**: Create board meeting, add resolutions, vote, check quorum
- **Reporting**: Create scheduled template, generate on-demand, download PDF
- **Real-time**: Open meeting in 2 tabs, add comment in one, see it appear in other
- **White-label**: Create reseller org, access via subdomain, verify branding
- **Offline**: Disconnect network, view cached meeting, add note, reconnect, verify sync
