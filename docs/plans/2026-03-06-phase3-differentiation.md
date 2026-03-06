# Phase 3: Differentiation — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build 8 features that differentiate antaraFlow — board compliance, SSO, analytics, real-time collaboration, advanced reporting, white-label, offline mode, and AI meeting preparation.

**Architecture:** Each feature is a bounded context under `app/Domain/` following existing DDD patterns. Models use `$guarded = ['id']`, `BelongsToOrganization` trait, and backed PHP 8.4 enums. Views are Blade + Alpine.js + Tailwind CSS 4. Tests use Pest 4 with `RefreshDatabase`.

**Tech Stack:** Laravel 12, Laravel Reverb (WebSocket), Laravel Socialite (OAuth), Chart.js, IndexedDB, Background Sync API, DomPDF.

**Baseline:** 624 tests passing, 39 models.

---

## Sprint 1

---

### Task 1: Feature 3.2 — SSO OAuth2 Social Login

#### Step 1: Install Socialite

Run:
```bash
composer require laravel/socialite --no-interaction
```

#### Step 2: Add OAuth config to services.php

Modify: `config/services.php` — Add after line 36 (before closing `];`):

```php
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => '/auth/google/callback',
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => '/auth/microsoft/callback',
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => '/auth/github/callback',
    ],
```

Update: `.env.example` — Add placeholder keys for all 3 providers.

#### Step 3: Create SocialAccount model + migration

Run:
```bash
php artisan make:model Domain/Account/Models/SocialAccount -m --no-interaction
```

Migration columns:
- `user_id` (foreignId, constrained, cascadeOnDelete)
- `provider` (string, 20) — google, microsoft, github
- `provider_id` (string)
- `provider_email` (string, nullable)
- `avatar_url` (string, 2048, nullable)
- `timestamps()`
- Unique index: `['provider', 'provider_id']`

Model: `$guarded = ['id']`, `belongsTo(User::class)`.

Create: `database/factories/Domain/Account/SocialAccountFactory.php`

#### Step 4: Create SocialAuthService

Create: `app/Domain/Account/Services/SocialAuthService.php`

Methods:
- `findOrCreateUser(string $provider, SocialiteUser $socialUser): User` — Find by SocialAccount match, then by email match (link account), then create new user + org
- `linkAccount(User $user, string $provider, SocialiteUser $socialUser): SocialAccount`
- `unlinkAccount(User $user, string $provider): void`

Key logic: On new user creation, create a default organization, attach as Owner, set `current_organization_id`.

#### Step 5: Create SocialAuthController

Create: `app/Domain/Account/Controllers/SocialAuthController.php`

Methods:
- `redirect(string $provider)` — Validate provider is in ['google', 'microsoft', 'github'], `Socialite::driver($provider)->redirect()`
- `callback(string $provider)` — Get Socialite user, call service, login, redirect to dashboard
- `unlink(string $provider)` — Unlink social account from authenticated user

#### Step 6: Add social login buttons to login/register views

Create: `resources/views/auth/partials/social-buttons.blade.php` — 3 buttons styled with Tailwind (Google red, Microsoft blue, GitHub gray).

Modify: `resources/views/auth/login.blade.php` — After the form (line 30), before the register link, add:
```blade
<div class="relative my-4">
    <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200"></div></div>
    <div class="relative flex justify-center text-xs"><span class="bg-white px-2 text-gray-500">or continue with</span></div>
</div>
@include('auth.partials.social-buttons')
```

Modify: `resources/views/auth/register.blade.php` — Same pattern.

#### Step 7: Create connected accounts settings view

Create: `resources/views/settings/connected-accounts.blade.php` — List linked providers with unlink buttons, show available providers with link buttons.

Modify: Organization settings navigation to add "Connected Accounts" link.

#### Step 8: Add routes

Modify: `routes/web.php` — Add in the guest middleware group:
```php
Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
```

Add in the auth middleware group:
```php
Route::delete('auth/{provider}/unlink', [SocialAuthController::class, 'unlink'])->name('social.unlink');
```

#### Step 9: Run migration, Pint, write tests

Run: `php artisan migrate --no-interaction`
Run: `vendor/bin/pint --dirty --format agent`

Create: `tests/Feature/Domain/Account/SocialAuthTest.php` — 8 tests:
1. Redirect to Google OAuth
2. Redirect to invalid provider returns 404
3. Callback creates new user from Google
4. Callback links existing user by email match
5. Callback logs in existing linked user
6. Unlink removes social account
7. Cannot unlink when it's the only auth method (no password set)
8. Social buttons visible on login page

Run: `php artisan test --compact`

#### Step 10: Commit

```bash
git add -A && git commit -m "feat: add SSO OAuth2 social login (Google, Microsoft, GitHub)"
```

---

### Task 2: Feature 3.10 — AI Meeting Preparation

#### Step 1: Add MeetingPreparation to ExtractionType enum

Modify: `app/Support/Enums/ExtractionType.php` — Add after line 13:
```php
case MeetingPreparation = 'meeting_preparation';
```

Add to `label()` method:
```php
self::MeetingPreparation => 'Meeting Preparation',
```

#### Step 2: Create MeetingPreparationService

Create: `app/Domain/AI/Services/MeetingPreparationService.php`

Method: `generate(MinutesOfMeeting $mom): array`

Logic:
1. Find last 3 meetings in same project: `MinutesOfMeeting::where('project_id', $mom->project_id)->where('id', '!=', $mom->id)->latest()->limit(3)->get()`
2. Gather open action items for attendees: `ActionItem::whereIn('assigned_to', $attendeeUserIds)->whereNotIn('status', [Completed, Cancelled])->get()`
3. Gather carried-forward items from previous meetings
4. Check for custom ExtractionTemplate (type: `meeting_preparation`)
5. Build prompt with context, call `$provider->chat()`
6. Parse JSON response: `suggested_agenda`, `carryover_items`, `discussion_topics`, `estimated_duration_minutes`

#### Step 3: Create MeetingPreparationController

Create: `app/Domain/AI/Controllers/MeetingPreparationController.php`

Methods:
- `generate(MinutesOfMeeting $meeting)` — Call service, return JSON
- `apply(Request $request, MinutesOfMeeting $meeting)` — Save selected agenda items to meeting (store in meeting `metadata` JSON field under `agenda` key)

#### Step 4: Create preparation modal view

Create: `resources/views/meetings/partials/preparation-modal.blade.php` — Alpine.js modal with:
- Loading state while AI generates
- Checklist of suggested agenda items (checkboxes)
- Carryover items section
- "Apply Selected" button

#### Step 5: Add button to meeting show view + routes

Modify: Meeting show view — Add "AI Prepare Agenda" button (visible when meeting is Draft/InProgress and has a project).

Modify: `routes/web.php` — In meetings prefix group add:
```php
Route::get('prepare-agenda', [MeetingPreparationController::class, 'generate'])->name('prepare-agenda.generate');
Route::post('prepare-agenda', [MeetingPreparationController::class, 'apply'])->name('prepare-agenda.apply');
```

#### Step 6: Run Pint, write tests

Run: `vendor/bin/pint --dirty --format agent`

Create: `tests/Feature/Domain/AI/MeetingPreparationTest.php` — 6 tests:
1. Generate returns suggested agenda JSON
2. Generate gathers context from past project meetings
3. Generate includes open action items
4. Apply saves agenda to meeting metadata
5. Cannot generate for non-draft meeting
6. Supports custom extraction template

Run: `php artisan test --compact`

#### Step 7: Commit

```bash
git add -A && git commit -m "feat: add AI meeting preparation with agenda suggestions"
```

---

### Task 3: Feature 3.4 — Meeting Governance Analytics

#### Step 1: Create GovernanceAnalyticsService

Create: `app/Domain/Analytics/Services/GovernanceAnalyticsService.php`

Methods (all accept `int $organizationId, ?Carbon $startDate, ?Carbon $endDate`):
- `getMeetingCostEstimate(float $hourlyRate = 50.0): array` — Sum of (duration_minutes / 60 * hourlyRate * attendee_count) per meeting
- `getAttendanceRateTrends(): array` — Monthly present/total ratio
- `getActionItemCompletionTrends(): array` — Monthly on-time vs overdue completions
- `getMeetingTypeDistribution(): array` — Count by meeting_type
- `getApprovalTurnaround(): array` — Avg days between finalized_at and approved_at (use audit log timestamps)
- `getComplianceScore(): array` — % meetings with: minutes approved, all action items assigned, on-time completion

#### Step 2: Create GovernanceAnalyticsController

Create: `app/Domain/Analytics/Controllers/GovernanceAnalyticsController.php`

Methods:
- `index()` — Return governance view
- `data(Request $request)` — JSON endpoint with date range filtering

#### Step 3: Create governance analytics view

Create: `resources/views/analytics/governance.blade.php` — Tab content with:
- Meeting cost summary card
- Attendance rate trend (line chart)
- Action item completion trends (bar chart)
- Meeting type distribution (doughnut chart)
- Approval turnaround (stat card)
- Compliance score (gauge/percentage)
- CSV export button

#### Step 4: Add governance tab to analytics index

Modify: `resources/views/analytics/index.blade.php` — Add tabbed navigation: "Overview" (existing) | "Governance" (new). Use Alpine.js tabs.

#### Step 5: Add routes

Modify: `routes/web.php` — Add:
```php
Route::get('analytics/governance', [GovernanceAnalyticsController::class, 'index'])->name('analytics.governance');
Route::get('analytics/governance/data', [GovernanceAnalyticsController::class, 'data'])->name('analytics.governance.data');
Route::get('analytics/governance/export', [GovernanceAnalyticsController::class, 'export'])->name('analytics.governance.export');
```

#### Step 6: Run Pint, write tests

Run: `vendor/bin/pint --dirty --format agent`

Create: `tests/Feature/Domain/Analytics/GovernanceAnalyticsTest.php` — 7 tests:
1. Governance page loads successfully
2. Meeting cost calculation is correct
3. Attendance rate calculates present/total
4. Action item completion trends include overdue count
5. Meeting type distribution groups correctly
6. Date range filtering works
7. CSV export downloads file

Run: `php artisan test --compact`

#### Step 7: Commit

```bash
git add -A && git commit -m "feat: add meeting governance analytics dashboard"
```

---

## Sprint 2

---

### Task 4: Feature 3.1 — Board Meeting Compliance Mode

#### Step 4a: Create enums

Create: `app/Support/Enums/ResolutionStatus.php` — Proposed, Passed, Failed, Tabled, Withdrawn (with `label()`)

Create: `app/Support/Enums/VoteChoice.php` — For, Against, Abstain (with `label()`)

#### Step 4b: Create BoardSetting model + migration

Run: `php artisan make:model Domain/Meeting/Models/BoardSetting -mf --no-interaction`

Migration columns:
- `organization_id` (foreignId, constrained, cascadeOnDelete)
- `quorum_type` (string, 20, default: 'percentage') — percentage or count
- `quorum_value` (integer, default: 50)
- `require_chair` (boolean, default: false)
- `require_secretary` (boolean, default: false)
- `voting_enabled` (boolean, default: true)
- `chair_casting_vote` (boolean, default: false)
- `block_finalization_without_quorum` (boolean, default: false)
- `timestamps()`
- Unique index on `organization_id`

Model: `$guarded = ['id']`, `BelongsToOrganization`, `belongsTo(Organization::class)`

#### Step 4c: Create MeetingResolution model + migration

Run: `php artisan make:model Domain/Meeting/Models/MeetingResolution -mf --no-interaction`

Migration columns:
- `meeting_id` (foreignId → minutes_of_meetings, cascadeOnDelete)
- `resolution_number` (string, 20)
- `title` (string)
- `description` (text, nullable)
- `mover_id` (foreignId → mom_attendees, nullable, nullOnDelete)
- `seconder_id` (foreignId → mom_attendees, nullable, nullOnDelete)
- `status` (string, 20, default: 'proposed')
- `timestamps()`
- Index on `['meeting_id', 'status']`

Model: `$guarded = ['id']`, `belongsTo(MinutesOfMeeting)`, `belongsTo(MomAttendee, 'mover_id')`, `belongsTo(MomAttendee, 'seconder_id')`, `hasMany(ResolutionVote)`, cast `status => ResolutionStatus::class`

#### Step 4d: Create ResolutionVote model + migration

Run: `php artisan make:model Domain/Meeting/Models/ResolutionVote -mf --no-interaction`

Migration columns:
- `resolution_id` (foreignId → meeting_resolutions, cascadeOnDelete)
- `attendee_id` (foreignId → mom_attendees, cascadeOnDelete)
- `vote` (string, 10) — for, against, abstain
- `voted_at` (timestamp)
- Unique index: `['resolution_id', 'attendee_id']`

Model: `$guarded = ['id']`, cast `vote => VoteChoice::class`, `voted_at => datetime`, `belongsTo(MeetingResolution)`, `belongsTo(MomAttendee, 'attendee_id')`

#### Step 4e: Create QuorumService

Create: `app/Domain/Meeting/Services/QuorumService.php`

Methods:
- `check(MinutesOfMeeting $meeting): array` — Returns `['is_met' => bool, 'required' => int, 'present' => int, 'type' => string]`
- `calculate(Organization $org, int $totalAttendees, int $presentAttendees): bool`
- `isQuorumMet(MinutesOfMeeting $meeting): bool`

Logic: Load org's BoardSetting, compare present vs required based on quorum_type (percentage = ceil(total * value/100), count = value directly).

#### Step 4f: Create ResolutionService

Create: `app/Domain/Meeting/Services/ResolutionService.php`

Methods:
- `create(MinutesOfMeeting $meeting, array $data): MeetingResolution` — Auto-generate resolution_number `RES-{Y}-{seq}`
- `update(MeetingResolution $resolution, array $data): MeetingResolution`
- `delete(MeetingResolution $resolution): void`
- `castVote(MeetingResolution $resolution, int $attendeeId, VoteChoice $vote): ResolutionVote`
- `calculateResult(MeetingResolution $resolution): ResolutionStatus` — Count for/against, apply chair casting if tied + enabled
- `getNextResolutionNumber(int $organizationId): string`

#### Step 4g: Create controllers, requests, policy

Create: `app/Domain/Meeting/Controllers/BoardSettingController.php` — `edit()`, `update()`
Create: `app/Domain/Meeting/Controllers/ResolutionController.php` — `store()`, `update()`, `destroy()`
Create: `app/Domain/Meeting/Controllers/VoteController.php` — `store()` (cast vote)
Create: `app/Domain/Meeting/Requests/UpdateBoardSettingRequest.php`
Create: `app/Domain/Meeting/Requests/CreateResolutionRequest.php`
Create: `app/Domain/Meeting/Requests/UpdateResolutionRequest.php`
Create: `app/Domain/Meeting/Requests/CastVoteRequest.php`
Create: `app/Domain/Meeting/Policies/ResolutionPolicy.php` — Uses meeting's authorization

#### Step 4h: Create views

Create: `resources/views/settings/board-settings.blade.php` — Form for quorum settings
Create: `resources/views/meetings/partials/board-compliance.blade.php` — Quorum badge + resolutions list
Create: `resources/views/meetings/partials/resolution-card.blade.php` — Single resolution with voting buttons + tally

#### Step 4i: Integrate into meeting show + finalization

Modify: `app/Domain/Meeting/Models/MinutesOfMeeting.php` — Add `resolutions()` hasMany relationship.

Modify: Meeting show view — Include `board-compliance` partial when `meeting_type === MeetingType::BoardMeeting`.

Modify: `app/Domain/Meeting/Services/MeetingService.php` — In `finalize()`, if board meeting + BoardSetting has `block_finalization_without_quorum`, check quorum before proceeding.

#### Step 4j: Register policy, add routes

Modify: `app/Providers/AppServiceProvider.php` — Add after line 79:
```php
Gate::policy(MeetingResolution::class, ResolutionPolicy::class);
```

Modify: `routes/web.php` — Add:
```php
Route::get('settings/board', [BoardSettingController::class, 'edit'])->name('settings.board.edit');
Route::put('settings/board', [BoardSettingController::class, 'update'])->name('settings.board.update');
```

In meetings prefix group:
```php
Route::resource('resolutions', ResolutionController::class)->only(['store', 'update', 'destroy']);
Route::post('resolutions/{resolution}/vote', [VoteController::class, 'store'])->name('resolutions.vote');
```

#### Step 4k: Run migrations, Pint, write tests

Run: `php artisan migrate --no-interaction`
Run: `vendor/bin/pint --dirty --format agent`

Create: `tests/Feature/Domain/Meeting/BoardComplianceTest.php` — 10 tests:
1. Board settings page loads
2. Update board settings saves correctly
3. Quorum check with percentage type
4. Quorum check with count type
5. Create resolution auto-generates number
6. Cast vote records correctly
7. Cannot vote twice on same resolution
8. Resolution passes with majority for votes
9. Chair casting vote breaks tie
10. Finalization blocked without quorum (when configured)

Create: `tests/Feature/Domain/Meeting/ResolutionVoteTest.php` — 5 tests:
1. Only present attendees can vote
2. Vote tally calculation is correct
3. Resolution status updates after all votes
4. Withdraw resolution changes status
5. Board compliance partial visible only for board meetings

Run: `php artisan test --compact`

#### Step 4l: Commit

```bash
git add -A && git commit -m "feat: add board meeting compliance with quorum, voting, and resolutions"
```

---

### Task 5: Feature 3.7 — Advanced Reporting

#### Step 5a: Create ReportType enum

Create: `app/Support/Enums/ReportType.php` — MonthlySummary, ActionItemStatus, AttendanceReport, GovernanceCompliance (with `label()`)

#### Step 5b: Create models + migrations

Run: `php artisan make:model Domain/Report/Models/ReportTemplate -mf --no-interaction`

ReportTemplate migration:
- `organization_id` (foreignId, constrained, cascadeOnDelete)
- `name` (string)
- `type` (string, 30)
- `filters` (json, nullable) — date range, project, meeting type filters
- `schedule` (string, nullable) — cron expression
- `recipients` (json, nullable) — array of email addresses
- `is_active` (boolean, default: true)
- `last_generated_at` (timestamp, nullable)
- `created_by` (foreignId → users, nullable)
- `timestamps()`

Run: `php artisan make:model Domain/Report/Models/GeneratedReport -mf --no-interaction`

GeneratedReport migration:
- `report_template_id` (foreignId → report_templates, cascadeOnDelete)
- `organization_id` (foreignId, constrained, cascadeOnDelete)
- `file_path` (string)
- `file_size` (integer, nullable)
- `parameters` (json, nullable)
- `generated_at` (timestamp)
- `timestamps()`

Models: `$guarded = ['id']`, `BelongsToOrganization`, appropriate relationships and casts.

#### Step 5c: Create report generators

Create: `app/Domain/Report/Services/ReportGeneratorService.php` — Orchestrator that dispatches to type-specific generators.

Create: `app/Domain/Report/Generators/MonthlySummaryGenerator.php` — Meetings held, durations, attendees, action items created/completed.

Create: `app/Domain/Report/Generators/ActionItemStatusGenerator.php` — All open/overdue items, by assignee, by priority.

Create: `app/Domain/Report/Generators/AttendanceReportGenerator.php` — Attendance rates by person, by meeting type.

Create: `app/Domain/Report/Generators/GovernanceComplianceGenerator.php` — Quorum %, resolutions passed, approval turnaround.

Each generator implements `generate(ReportTemplate $template): string` (returns file path to generated PDF).

#### Step 5d: Create PDF templates

Create: `resources/views/reports/pdf/monthly-summary.blade.php`
Create: `resources/views/reports/pdf/action-item-status.blade.php`
Create: `resources/views/reports/pdf/attendance-report.blade.php`
Create: `resources/views/reports/pdf/governance-compliance.blade.php`

Each uses DomPDF-compatible HTML/CSS (existing pattern from meeting PDF export).

#### Step 5e: Create GenerateReportJob + ReportReadyMail

Create: `app/Domain/Report/Jobs/GenerateReportJob.php` — Calls generator, stores file, sends email to recipients.

Create: `app/Domain/Report/Mail/ReportReadyMail.php` — Mailable with download link.

#### Step 5f: Create controllers, requests, policy

Create: `app/Domain/Report/Controllers/ReportTemplateController.php` — Full CRUD + `generate()` (on-demand)
Create: `app/Domain/Report/Controllers/GeneratedReportController.php` — `index()`, `download()`
Create: `app/Domain/Report/Requests/CreateReportTemplateRequest.php`
Create: `app/Domain/Report/Requests/UpdateReportTemplateRequest.php`
Create: `app/Domain/Report/Policies/ReportTemplatePolicy.php` — Uses `manage_settings` permission

#### Step 5g: Create views

Create: `resources/views/reports/templates/index.blade.php` — List templates with status, last generated, schedule
Create: `resources/views/reports/templates/create.blade.php` — Form with type, filters, schedule, recipients
Create: `resources/views/reports/templates/edit.blade.php`
Create: `resources/views/reports/generated/index.blade.php` — History with download links

#### Step 5h: Create scheduled command

Create: `app/Console/Commands/GenerateScheduledReportsCommand.php`

Logic: Query active templates with non-null schedule, check if due (using `lorisleiva/cron-translator` or simple cron matching), dispatch `GenerateReportJob` for each.

Register in `routes/console.php`:
```php
Schedule::command('reports:generate-scheduled')->hourly();
```

#### Step 5i: Register policy, add routes

Modify: `app/Providers/AppServiceProvider.php` — Add policy for ReportTemplate.

Modify: `routes/web.php` — Add:
```php
Route::resource('reports', ReportTemplateController::class);
Route::post('reports/{report}/generate', [ReportTemplateController::class, 'generate'])->name('reports.generate');
Route::get('reports/generated', [GeneratedReportController::class, 'index'])->name('reports.generated.index');
Route::get('reports/generated/{report}/download', [GeneratedReportController::class, 'download'])->name('reports.generated.download');
```

#### Step 5j: Run migrations, Pint, write tests

Run: `php artisan migrate --no-interaction`
Run: `vendor/bin/pint --dirty --format agent`

Create: `tests/Feature/Domain/Report/ReportTemplateTest.php` — 8 tests:
1. Index page loads
2. Create template with valid data
3. Update template
4. Delete template
5. Viewer cannot access reports (policy)
6. Validation rejects invalid schedule

Create: `tests/Feature/Domain/Report/ReportGenerationTest.php` — 6 tests:
1. Monthly summary generator produces PDF
2. Action item status generator produces PDF
3. On-demand generate dispatches job
4. Generated report appears in history
5. Download returns file
6. Scheduled command finds due templates

Run: `php artisan test --compact`

#### Step 5k: Commit

```bash
git add -A && git commit -m "feat: add advanced reporting with templates, scheduling, and PDF generation"
```

---

## Sprint 3

---

### Task 6: Feature 3.6 — Real-time Collaboration (Laravel Reverb)

#### Step 6a: Install Reverb + Echo

Run:
```bash
composer require laravel/reverb --no-interaction
php artisan install:broadcasting --no-interaction
npm install laravel-echo pusher-js --save
```

This creates `config/broadcasting.php`, `config/reverb.php`, `routes/channels.php`.

#### Step 6b: Configure broadcasting

Verify/update `config/broadcasting.php` — Set default to `reverb`.

Update `.env.example` with Reverb credentials:
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=antaraflow
REVERB_APP_KEY=antaraflow-key
REVERB_APP_SECRET=antaraflow-secret
REVERB_HOST=localhost
REVERB_PORT=8080
```

#### Step 6c: Create broadcastable events

Create: `app/Events/CommentAdded.php` — Implements ShouldBroadcast, broadcasts on `PrivateChannel("meeting.{$meetingId}")`, payload: comment data + user name.

Create: `app/Events/MeetingStatusChanged.php` — Broadcasts on `PrivateChannel("meeting.{$meetingId}")`, payload: new status.

Create: `app/Events/ActionItemUpdated.php` — Broadcasts on `PrivateChannel("meeting.{$meetingId}")`, payload: action item data.

Create: `app/Events/AttendeePresenceChanged.php` — Broadcasts on `PresenceChannel("meeting.{$meetingId}.presence")`.

#### Step 6d: Configure channel authorization

Modify: `routes/channels.php`:
```php
Broadcast::channel('meeting.{meetingId}', function (User $user, int $meetingId) {
    $meeting = MinutesOfMeeting::find($meetingId);
    return $meeting && $meeting->organization_id === $user->current_organization_id;
});

Broadcast::channel('meeting.{meetingId}.presence', function (User $user, int $meetingId) {
    $meeting = MinutesOfMeeting::find($meetingId);
    if ($meeting && $meeting->organization_id === $user->current_organization_id) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    return false;
});

Broadcast::channel('organization.{orgId}', function (User $user, int $orgId) {
    return $user->current_organization_id === $orgId;
});
```

#### Step 6e: Set up Echo on frontend

Modify: `resources/js/bootstrap.js` — Add after axios config:
```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

Update `.env.example` with VITE_REVERB_* variables.

#### Step 6f: Create Alpine.js live meeting component

Create: `resources/js/meeting-live.js` — Alpine.js data component:
- Joins meeting private channel + presence channel on init
- Listens for CommentAdded → appends to comments list
- Listens for MeetingStatusChanged → updates status badge
- Listens for ActionItemUpdated → updates action item in list
- Tracks presence: `viewers` array of online users
- Whisper events for typing indicator

#### Step 6g: Dispatch events from services

Modify: `app/Domain/Collaboration/Services/CommentService.php` — In `addComment()`, after creating comment, dispatch `CommentAdded` event.

Modify: `app/Domain/Meeting/Services/MeetingService.php` — In `update()` when status changes, dispatch `MeetingStatusChanged`.

Modify: `app/Domain/ActionItem/Services/ActionItemService.php` — In `update()`, dispatch `ActionItemUpdated`.

#### Step 6h: Update meeting show view

Modify: Meeting show view — Add `x-data="meetingLive({{ $meeting->id }})"` wrapper, presence avatars in header, live comment feed.

Modify: `resources/js/app.js` — Import and register `meetingLive` Alpine component.

#### Step 6i: Run Pint, build frontend, write tests

Run: `vendor/bin/pint --dirty --format agent`
Run: `npm run build`

Create: `tests/Feature/Broadcasting/MeetingBroadcastTest.php` — 6 tests:
1. CommentAdded event broadcasts on correct channel
2. MeetingStatusChanged event broadcasts
3. ActionItemUpdated event broadcasts
4. Channel auth allows org member
5. Channel auth rejects non-member
6. Presence channel returns user data

Run: `php artisan test --compact`

#### Step 6j: Commit

```bash
git add -A && git commit -m "feat: add real-time collaboration with Laravel Reverb"
```

---

### Task 7: Feature 3.8 — White-label Reseller Program

#### Step 7a: Add parent_organization_id migration

Create migration: `xxxx_add_parent_org_to_organizations_table.php`
- `parent_organization_id` (foreignId → organizations, nullable, nullOnDelete)

#### Step 7b: Create ResellerSetting model + migration

Run: `php artisan make:model Domain/Account/Models/ResellerSetting -mf --no-interaction`

Migration columns:
- `organization_id` (foreignId, constrained, cascadeOnDelete, unique)
- `subdomain` (string, 63, nullable, unique)
- `custom_domain` (string, nullable, unique)
- `is_reseller` (boolean, default: false)
- `allowed_plans` (json, nullable) — plan IDs this reseller can offer
- `commission_rate` (decimal 5,2, default: 0)
- `max_sub_organizations` (integer, nullable)
- `branding_overrides` (json, nullable) — logo, colors, app_name, custom_css
- `timestamps()`

Model: `$guarded = ['id']`, `belongsTo(Organization::class)`, casts for JSON/boolean fields.

#### Step 7c: Create ResolveSubdomain middleware

Create: `app/Http/Middleware/ResolveSubdomain.php`

Logic:
1. Extract subdomain from `$request->getHost()` (e.g., `acme.antaraflow.test` → `acme`)
2. Also check `custom_domain` field for CNAME domains
3. If match found: bind org to request, override BrandingService with org's `branding_overrides`
4. If no match: continue normally (platform default)

Register in `bootstrap/app.php` → web middleware group.

#### Step 7d: Create ResellerService

Create: `app/Domain/Account/Services/ResellerService.php`

Methods:
- `getSubOrganizations(Organization $reseller): Collection`
- `createSubOrganization(Organization $reseller, array $data): Organization` — Set `parent_organization_id`
- `getUsageSummary(Organization $reseller): array` — Aggregate usage across sub-orgs
- `calculateCommission(Organization $reseller, string $period): float`

#### Step 7e: Create ResellerController + views

Create: `app/Domain/Account/Controllers/ResellerController.php` — `dashboard()`, `subOrganizations()`, `createSubOrg()`, `storeSubOrg()`
Create: `app/Domain/Account/Requests/UpdateResellerSettingRequest.php`
Create: `resources/views/reseller/dashboard.blade.php` — Usage stats, commission, sub-org count
Create: `resources/views/reseller/sub-organizations.blade.php` — List/create sub-orgs

#### Step 7f: Extend BrandingService for org-level overrides

Modify: `app/Domain/Admin/Services/BrandingService.php` — Add method `getForOrganization(?Organization $org): array` that merges org's `branding_overrides` on top of platform defaults.

Modify: Layout views — The `$branding` variable should resolve org-level overrides when accessed via subdomain.

#### Step 7g: Customize login page per subdomain

Modify: `resources/views/layouts/guest.blade.php` — Show org logo/name when subdomain org is resolved.

#### Step 7h: Add routes, register middleware

Modify: `routes/web.php` — Add reseller management routes (auth + org owner):
```php
Route::prefix('reseller')->name('reseller.')->group(function () {
    Route::get('/', [ResellerController::class, 'dashboard'])->name('dashboard');
    Route::get('sub-organizations', [ResellerController::class, 'subOrganizations'])->name('sub-organizations');
    Route::get('sub-organizations/create', [ResellerController::class, 'createSubOrg'])->name('sub-organizations.create');
    Route::post('sub-organizations', [ResellerController::class, 'storeSubOrg'])->name('sub-organizations.store');
});
```

Admin panel: Add reseller management (toggle is_reseller, set commission_rate).

#### Step 7i: Run migrations, Pint, write tests

Run: `php artisan migrate --no-interaction`
Run: `vendor/bin/pint --dirty --format agent`

Create: `tests/Feature/Domain/Account/ResellerTest.php` — 8 tests:
1. Reseller dashboard loads for reseller org
2. Non-reseller cannot access reseller pages
3. Create sub-organization sets parent_id
4. Subdomain middleware resolves correct org
5. Custom domain middleware resolves correct org
6. Branding overrides applied via subdomain
7. Sub-org count respects max limit
8. Login page shows org branding via subdomain

Run: `php artisan test --compact`

#### Step 7j: Commit

```bash
git add -A && git commit -m "feat: add white-label reseller program with subdomain routing"
```

---

### Task 8: Feature 3.9 — Offline Mode (Read-only + Draft Queue)

#### Step 8a: Create offline data endpoint

Create: `app/Domain/Meeting/Controllers/OfflineDataController.php`

Methods:
- `show(MinutesOfMeeting $meeting)` — Returns full meeting JSON (title, attendees, action items, extractions, notes) for offline caching
- `sync(Request $request)` — Receives queued offline actions (notes, comments), processes them

Modify: `routes/web.php` — Add:
```php
Route::get('meetings/{meeting}/offline-data', [OfflineDataController::class, 'show'])->name('meetings.offline-data');
Route::post('offline/sync', [OfflineDataController::class, 'sync'])->name('offline.sync');
```

#### Step 8b: Create IndexedDB store

Create: `resources/js/offline-store.js`

Exports Alpine-compatible module:
- `initDB()` — Open IndexedDB `antaraflow-offline` with stores: `meetings`, `offline_actions`
- `cacheMeeting(id, data)` — Store meeting JSON with timestamp
- `getCachedMeeting(id)` — Retrieve from IndexedDB
- `getCachedMeetingsList()` — Return cached meeting IDs + titles
- `addOfflineAction(type, meetingId, payload)` — Queue offline action
- `getPendingActions()` — Get all unsynced actions
- `markActionSynced(id)` — Mark as synced
- `evictOldMeetings(maxCount = 50)` — LRU eviction

#### Step 8c: Create offline queue + sync

Create: `resources/js/offline-queue.js`

Exports:
- `syncPendingActions()` — POST each pending action to `/offline/sync`, mark as synced on success
- `registerBackgroundSync()` — Register SW background sync tag `offline-sync`

#### Step 8d: Create online status Alpine component

Create: `resources/js/online-status.js` — Alpine data component:
- `isOnline` reactive property (tracks `navigator.onLine`)
- `pendingCount` — count of unsynced actions
- `syncNow()` — trigger manual sync
- Listens to `online`/`offline` events, auto-syncs on reconnect

#### Step 8e: Create offline indicator component

Create: `resources/views/components/offline-indicator.blade.php` — Fixed banner at top: "You're offline — X items pending sync" with "Sync Now" button.

#### Step 8f: Enhance service worker

Modify: `public/sw.js` — Add:
1. Cache meeting offline-data JSON responses (`/meetings/*/offline-data`)
2. Register background sync handler for `offline-sync` tag
3. Pre-cache dashboard page on install
4. Handle offline POST requests by returning queued response

#### Step 8g: Integrate into meeting show view

Modify: Meeting show view — On page load, cache meeting data to IndexedDB via `offline-store.js`. Include `offline-indicator` component.

Modify: `resources/js/app.js` — Import and register `onlineStatus` Alpine component.

Modify: `resources/views/layouts/app.blade.php` — Include `<x-offline-indicator />` before main content.

#### Step 8h: Run Pint, build frontend, write tests

Run: `vendor/bin/pint --dirty --format agent`
Run: `npm run build`

Create: `tests/Feature/OfflineModeTest.php` — 5 tests:
1. Offline data endpoint returns full meeting JSON
2. Sync endpoint processes queued notes
3. Sync endpoint processes queued comments
4. Offline data requires authentication
5. Sync rejects invalid action types

Run: `php artisan test --compact`

#### Step 8i: Commit

```bash
git add -A && git commit -m "feat: add offline mode with IndexedDB caching and background sync"
```

---

## Final Verification

After all tasks:

1. Run: `vendor/bin/pint --format agent` (full codebase)
2. Run: `php artisan test --compact` (all tests must pass)
3. Run: `npm run build` (frontend must compile)
4. Verify test count is approximately 624 + ~70 new ≈ 694+ tests passing
