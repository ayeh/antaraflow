# Phase 1: Foundation — Design Document

> **Date**: 5 March 2026
> **Status**: Approved
> **Scope**: 7 features (1.1, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8)
> **Excluded**: 1.2 Stripe/Paddle billing integration

---

## Table of Contents

1. [1.1 Calendar Two-Way Sync](#11-calendar-two-way-sync)
2. [1.3 Free Tier Enforcement](#13-free-tier-enforcement)
3. [1.4 Email Notification Automation](#14-email-notification-automation)
4. [1.5 Global Search](#15-global-search)
5. [1.6 Onboarding Wizard](#16-onboarding-wizard)
6. [1.7 API Rate Limiting](#17-api-rate-limiting)
7. [1.8 Event Listeners](#18-event-listeners)
8. [Implementation Order](#implementation-order)

---

## 1.1 Calendar Two-Way Sync

### Overview

Two-way calendar sync with Google Calendar and Microsoft Outlook via OAuth 2.0. Meetings created in antaraFlow appear in the user's calendar, and calendar events can create/update meetings in antaraFlow.

### Architecture

```
Google Calendar ←→ antaraFlow ←→ Outlook Calendar
       ↓                              ↓
   Google OAuth 2.0            Microsoft OAuth 2.0
       ↓                              ↓
   Calendar API                  Graph API
       ↓                              ↓
   Webhooks (push)             Subscriptions (push)
       ↓                              ↓
       └──── CalendarSyncService ─────┘
                    ↓
           MinutesOfMeeting model
```

### New Domain: `app/Domain/Calendar/`

#### Models

**`CalendarConnection`** — stores OAuth tokens per user:

| Column | Type | Description |
|--------|------|-------------|
| `id` | ulid | Primary key |
| `user_id` | foreignId | User who connected |
| `organization_id` | foreignId | Org context |
| `provider` | string | `google` or `outlook` |
| `access_token` | text (encrypted) | OAuth access token |
| `refresh_token` | text (encrypted) | OAuth refresh token |
| `token_expires_at` | timestamp | Token expiry |
| `calendar_id` | string (nullable) | Selected calendar ID |
| `webhook_channel_id` | string (nullable) | For Google push notifications |
| `webhook_expiry` | timestamp (nullable) | Webhook expiry |
| `is_active` | boolean | Connection active |
| `timestamps` | — | — |

**Migration on `minutes_of_meetings`** — add:

| Column | Type | Description |
|--------|------|-------------|
| `calendar_event_id` | string (nullable) | External calendar event ID |
| `calendar_provider` | string (nullable) | Provider name |
| `calendar_synced_at` | timestamp (nullable) | Last sync timestamp |

#### Contract

```php
interface CalendarProviderInterface
{
    public function getAuthUrl(string $redirectUri): string;
    public function handleCallback(string $code, string $redirectUri): array; // tokens
    public function refreshToken(CalendarConnection $connection): CalendarConnection;
    public function createEvent(CalendarConnection $connection, MinutesOfMeeting $meeting): string; // event_id
    public function updateEvent(CalendarConnection $connection, MinutesOfMeeting $meeting): void;
    public function deleteEvent(CalendarConnection $connection, string $eventId): void;
    public function listCalendars(CalendarConnection $connection): array;
    public function registerWebhook(CalendarConnection $connection): void;
    public function handleWebhook(Request $request): array; // changed events
}
```

#### Providers

- `GoogleCalendarProvider` — uses `google/apiclient` package
- `OutlookCalendarProvider` — uses `microsoft/microsoft-graph` package

#### Services

**`CalendarSyncService`**:
- `syncToCalendar(MinutesOfMeeting $meeting)` — push meeting to all connected calendars for org members
- `syncFromCalendar(array $eventData, CalendarConnection $connection)` — create/update meeting from calendar event
- `resolveConflict(MinutesOfMeeting $meeting, array $eventData)` — last-write-wins by timestamp; calendar changes only update date/time/location, never MoM content

#### Routes

| Method | Route | Action |
|--------|-------|--------|
| `GET` | `/calendar/connections` | List connected calendars |
| `GET` | `/calendar/connect/{provider}` | Redirect to OAuth consent |
| `GET` | `/calendar/callback/{provider}` | Handle OAuth callback |
| `DELETE` | `/calendar/disconnect/{connection}` | Revoke + delete connection |
| `POST` | `/calendar/webhook/{provider}` | Receive webhook from provider |
| `POST` | `/calendar/sync/{meeting}` | Manual sync trigger |

#### Packages

```
composer require google/apiclient
composer require microsoft/microsoft-graph
```

#### Config

New `config/calendar.php`:

```php
return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
    ],
    'outlook' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect_uri' => env('MICROSOFT_CALENDAR_REDIRECT_URI'),
        'tenant_id' => env('MICROSOFT_TENANT_ID', 'common'),
    ],
];
```

#### Sync Flow

**antaraFlow → Calendar:**
1. Meeting created/updated/deleted → `MeetingService` dispatches `MeetingCreated`/`MeetingUpdated`/`MeetingDeleted` event
2. `SyncMeetingToCalendar` listener fires
3. For each org member with an active `CalendarConnection`: push event via provider
4. Store `calendar_event_id` on meeting

**Calendar → antaraFlow:**
1. Google/Outlook sends webhook to `/calendar/webhook/{provider}`
2. `CalendarWebhookController` validates signature, fetches changed events
3. For events with matching `calendar_event_id`: update meeting date/time/location
4. For new events (no match): create draft meeting with basic info
5. Conflict: calendar changes only update `meeting_date`, `start_time`, `end_time`, `location`. Never touch `content`, `summary`, `status`.

---

## 1.3 Free Tier Enforcement

### Overview

Enforce subscription plan limits with soft blocks and upgrade CTAs. Users can view existing data but cannot create new resources when limits are reached.

### `SubscriptionService`

Location: `app/Domain/Account/Services/SubscriptionService.php`

```php
class SubscriptionService
{
    public function canPerform(Organization $org, string $metric): bool;
    public function checkLimit(Organization $org, string $metric): void; // throws LimitExceededException
    public function getCurrentUsage(Organization $org, string $metric): int;
    public function getPlanLimit(Organization $org, string $metric): ?int; // null = unlimited
    public function incrementUsage(Organization $org, string $metric, int $amount = 1): void;
    public function decrementUsage(Organization $org, string $metric, int $amount = 1): void;
    public function resetMonthlyUsage(Organization $org): void;
    public function isFeatureEnabled(Organization $org, string $feature): bool;
}
```

### Metric Enforcement Points

| Metric | Plan Field | Enforced In | Trigger |
|--------|-----------|-------------|---------|
| `meetings` | `max_meetings_per_month` | `MeetingService::create()` | Before creating meeting |
| `audio_minutes` | `max_audio_minutes_per_month` | `ProcessTranscriptionJob::handle()` | After transcription completes (actual duration) |
| `storage_mb` | `max_storage_mb` | Upload controllers (audio, documents) | Before accepting upload |
| `members` | `max_users` | `OrganizationMemberController::store()` | Before adding member |

### Feature Flag Enforcement

| Feature Key | Plan Field | Enforced In | Behavior When Disabled |
|-------------|-----------|-------------|----------------------|
| `ai_summaries` | `features.ai_summaries` | `ExtractionController` | Show upgrade CTA |
| `export` | `features.export` | `ExportController` | Show upgrade CTA |
| `api_access` | `features.api_access` | `ApiKeyController::store()` | Show upgrade CTA |
| `meeting_series` | `features.meeting_series` | `MeetingSeriesController::store()` | Show upgrade CTA |
| `templates` | `features.templates` | `MeetingTemplateController::store()` | Show upgrade CTA |
| `analytics` | `features.analytics` | `AnalyticsController::index()` | Show upgrade CTA |

### Exception & Response

**`LimitExceededException`** (extends `\Exception`):
- Properties: `$metric`, `$currentUsage`, `$limit`, `$planName`

**Controller handling:**
```php
try {
    $this->subscriptionService->checkLimit($org, 'meetings');
    // proceed with creation
} catch (LimitExceededException $e) {
    if ($request->expectsJson()) {
        return response()->json([
            'limited' => true,
            'metric' => $e->metric,
            'current' => $e->currentUsage,
            'limit' => $e->limit,
            'upgrade_url' => route('subscription.index'),
        ], 403);
    }
    return redirect()->route('subscription.index')
        ->with('limit_exceeded', $e->getMessage());
}
```

### Usage Reset

**Scheduled job:** `ResetMonthlyUsageJob` — runs on the 1st of each month, resets `meetings` and `audio_minutes` usage for all orgs.

### Upgrade CTA View

`resources/views/subscription/limit-reached.blade.php` — modal/banner with:
- Current plan name
- Which limit was reached
- Current vs max usage
- "Upgrade Plan" button → subscription page

---

## 1.4 Email Notification Automation

### Overview

Wire existing events to listeners that dispatch notifications via mail and database channels, using admin-managed email templates and per-org SMTP configuration.

### Event → Listener → Notification Map

| Event | Listener | Notification Class | Channel |
|-------|----------|-------------------|---------|
| `TranscriptionCompleted` | `NotifyTranscriptionComplete` | `TranscriptionCompletedNotification` | database + mail |
| `TranscriptionFailed` | `NotifyTranscriptionFailed` | `TranscriptionFailedNotification` | database + mail |
| `ExtractionCompleted` | `NotifyExtractionComplete` | `ExtractionCompletedNotification` | database + mail |
| `ExtractionFailed` | `NotifyExtractionFailed` | `ExtractionFailedNotification` | database + mail |
| **New:** `MeetingFinalized` | `NotifyMeetingFinalized` | `MeetingFinalizedNotification` (existing) | database + **mail** |
| **New:** `MeetingApproved` | `NotifyMeetingApproved` | `MeetingApprovedNotification` (new) | database + mail |
| **Inline** (no event) | — | `MeetingInviteNotification` (existing) | database + mail |
| **Inline** (no event) | — | `ActionItemAssignedNotification` (existing) | database + mail |
| **Inline** (register) | — | `WelcomeNotification` (new) | mail only |

### New Events

**`MeetingFinalized`** (`app/Domain/Meeting/Events/`):
- Properties: `MinutesOfMeeting $meeting`, `User $finalizedBy`
- Dispatched in: `MeetingService::finalize()`

**`MeetingApproved`** (`app/Domain/Meeting/Events/`):
- Properties: `MinutesOfMeeting $meeting`, `User $approvedBy`
- Dispatched in: `MeetingService::approve()`

### EmailTemplate Integration

Override `toMail()` in each notification to use `EmailTemplate`:

```php
public function toMail(object $notifiable): MailMessage
{
    $template = EmailTemplate::findBySlug('meeting-finalized');

    if ($template) {
        $rendered = $template->render([
            'user_name' => $notifiable->name,
            'meeting_title' => $this->meeting->title,
            'meeting_url' => route('meetings.show', $this->meeting),
        ]);

        return (new MailMessage)
            ->subject($template->renderSubject($variables))
            ->view('emails.template', ['content' => $rendered]);
    }

    // Fallback to MailMessage builder
    return (new MailMessage)
        ->subject('Meeting Finalized: ' . $this->meeting->title)
        ->line('The meeting has been finalized.')
        ->action('View Meeting', route('meetings.show', $this->meeting));
}
```

### SMTP Configuration Hook

Listen to `Illuminate\Mail\Events\MessageSending` event:

```php
// In AppServiceProvider::boot()
Event::listen(MessageSending::class, function (MessageSending $event) {
    if ($orgId = $event->data['organization_id'] ?? null) {
        app(SmtpService::class)->applyConfig($orgId);
    }
});
```

### Notification Trigger Points

| Trigger Point | File | Action |
|---------------|------|--------|
| User registers | `RegisterController::register()` | `$user->notify(new WelcomeNotification)` |
| Attendee invited | `AttendeeController::store()` / `bulkInvite()` | Notify if attendee has user account |
| Action item assigned | `ActionItemController::store()` / `update()` | Notify assigned user |
| Meeting finalized | `MeetingService::finalize()` | Dispatch `MeetingFinalized` event |
| Meeting approved | `MeetingService::approve()` | Dispatch `MeetingApproved` event |

### New Email Templates to Seed

| Slug | Subject | Variables |
|------|---------|-----------|
| `transcription-completed` | Transcription Complete: {{meeting_title}} | user_name, meeting_title, meeting_url |
| `transcription-failed` | Transcription Failed: {{meeting_title}} | user_name, meeting_title, meeting_url, error_message |
| `extraction-completed` | AI Extraction Complete: {{meeting_title}} | user_name, meeting_title, meeting_url |
| `extraction-failed` | AI Extraction Failed: {{meeting_title}} | user_name, meeting_title, meeting_url, error_message |
| `meeting-approved` | Meeting Approved: {{meeting_title}} | user_name, meeting_title, meeting_url, approved_by |

---

## 1.5 Global Search

### Overview

Cross-entity search using database LIKE queries, exposed via an enhanced command palette (Cmd+K).

### `GlobalSearchService`

Location: `app/Domain/Search/Services/GlobalSearchService.php`

```php
class GlobalSearchService
{
    public function search(string $query, int $organizationId, int $limit = 20): array
    {
        return [
            'meetings' => $this->searchMeetings($query, $organizationId, 5),
            'action_items' => $this->searchActionItems($query, $organizationId, 5),
            'projects' => $this->searchProjects($query, $organizationId, 3),
            'manual_notes' => $this->searchManualNotes($query, $organizationId, 4),
            'transcriptions' => $this->searchTranscriptions($query, $organizationId, 3),
        ];
    }
}
```

### Search Fields Per Entity

| Entity | Model | Searchable Fields | Result Display |
|--------|-------|-------------------|----------------|
| Meetings | `MinutesOfMeeting` | title, mom_number, summary, content | Title + MOM number + status badge |
| Action Items | `ActionItem` | title, description | Title + status + priority badge |
| Projects | `Project` | name, description | Name + meeting count |
| Manual Notes | `MomManualNote` | title, content | Title + parent meeting title |
| Transcriptions | `AudioTranscription` | full_text | Snippet + parent meeting title |

### Search Controller

Location: `app/Domain/Search/Controllers/SearchController.php`

```php
class SearchController extends Controller
{
    public function index(Request $request, GlobalSearchService $search): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);

        $results = $search->search(
            $request->q,
            $request->user()->current_organization_id
        );

        return response()->json($results);
    }
}
```

### Route

```php
Route::get('/search', [SearchController::class, 'index'])->name('search');
```

### Command Palette Enhancement

Update `resources/views/layouts/partials/command-palette.blade.php`:

- Replace static `$recentMeetings` with Alpine.js `fetch()` call
- Debounce input at 300ms, minimum 2 characters
- Show recent items when query is empty (keep existing behavior)
- Group results by entity type with icons:
  - Meetings: document icon
  - Action Items: checkbox icon
  - Projects: folder icon
  - Notes: pencil icon
  - Transcriptions: microphone icon
- Keyboard navigation: arrow up/down + enter to navigate
- Each result links to its detail page

---

## 1.6 Onboarding Wizard

### Overview

3-step wizard for new users after registration. Middleware gates access until onboarding is complete.

### Database Change

Migration: add `onboarding_completed_at` (nullable timestamp) to `users` table.

### Steps

| Step | Title | Fields | Validation | Optional |
|------|-------|--------|-----------|----------|
| 1 | Complete Your Profile | Name (pre-filled from registration), avatar (file upload) | Name required, avatar image/max:2048 | Avatar optional |
| 2 | Setup Organization | Org name (pre-filled), logo (file upload), timezone (select), language (select) | Name required, timezone required | Logo optional |
| 3 | Invite Your Team | Email addresses (multi-input, comma separated), role per invite (select) | Emails valid format, role in UserRole | Entirely skippable |

### Controller

Location: `app/Domain/Account/Controllers/OnboardingController.php`

```php
class OnboardingController extends Controller
{
    public function step1();          // GET /onboarding/step/1
    public function updateStep1();    // POST /onboarding/step/1
    public function step2();          // GET /onboarding/step/2
    public function updateStep2();    // POST /onboarding/step/2
    public function step3();          // GET /onboarding/step/3
    public function updateStep3();    // POST /onboarding/step/3
    public function skip();           // POST /onboarding/skip
    public function complete();       // POST /onboarding/complete (private, called internally)
}
```

### Middleware

`OnboardingMiddleware` — checks `auth()->user()->onboarding_completed_at`:
- If null and route is not in excluded list → redirect to `/onboarding/step/1`
- Excluded routes: `login`, `logout`, `register`, `onboarding.*`, `api.*`, `admin.*`, `calendar/callback.*`
- Applied after `auth` middleware in the web group

### Routes

```php
Route::middleware(['auth'])->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/step/{step}', [OnboardingController::class, 'show'])->name('step');
    Route::post('/step/{step}', [OnboardingController::class, 'update'])->name('update');
    Route::post('/skip', [OnboardingController::class, 'skip'])->name('skip');
});
```

### Views

Location: `resources/views/onboarding/`

- `layout.blade.php` — minimal layout with progress bar (no sidebar/nav)
- `step1.blade.php` — profile form
- `step2.blade.php` — org setup form
- `step3.blade.php` — invite team form with "Skip" button

### Post-Completion

- Sets `onboarding_completed_at = now()` on user
- Redirect to `dashboard` with flash: "Welcome to antaraFlow! Create your first meeting to get started."

---

## 1.7 API Rate Limiting

### Overview

Laravel built-in rate limiter, 60 requests/minute per API key.

### Implementation

**In `bootstrap/app.php`** (inside `withMiddleware`):

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('api', function (Request $request) {
    $apiKey = $request->attributes->get('api_key');
    return Limit::perMinute(60)->by($apiKey?->id ?: $request->ip());
});
```

**In `routes/api.php`**:

Add `throttle:api` middleware to the existing route group.

### Response (429)

```json
{
    "message": "Too many requests. Please try again later.",
    "retry_after": 42
}
```

Headers: `Retry-After: 42`, `X-RateLimit-Limit: 60`, `X-RateLimit-Remaining: 0`

---

## 1.8 Event Listeners

### Overview

Create listeners for all existing events and 2 new events. Register in `AppServiceProvider::boot()`.

### New Events

**`MeetingFinalized`** (`app/Domain/Meeting/Events/MeetingFinalized.php`):

```php
class MeetingFinalized
{
    public function __construct(
        public MinutesOfMeeting $meeting,
        public User $finalizedBy,
    ) {}
}
```

**`MeetingApproved`** (`app/Domain/Meeting/Events/MeetingApproved.php`):

```php
class MeetingApproved
{
    public function __construct(
        public MinutesOfMeeting $meeting,
        public User $approvedBy,
    ) {}
}
```

### Listeners

| Listener | Domain | Event | Action |
|----------|--------|-------|--------|
| `TriggerAiExtraction` | Transcription | `TranscriptionCompleted` | Dispatch `ExtractMeetingDataJob` if meeting has no extraction |
| `NotifyTranscriptionComplete` | Transcription | `TranscriptionCompleted` | Notify meeting creator via database channel |
| `NotifyTranscriptionFailed` | Transcription | `TranscriptionFailed` | Notify meeting creator via database + mail |
| `NotifyExtractionComplete` | AI | `ExtractionCompleted` | Notify meeting creator via database channel |
| `NotifyExtractionFailed` | AI | `ExtractionFailed` | Notify meeting creator via database + mail |
| `NotifyMeetingFinalized` | Meeting | `MeetingFinalized` | Notify all attendees via database + mail |
| `NotifyMeetingApproved` | Meeting | `MeetingApproved` | Notify all attendees via database + mail |

### Registration

In `AppServiceProvider::boot()`:

```php
Event::listen(TranscriptionCompleted::class, [
    TriggerAiExtraction::class,
    NotifyTranscriptionComplete::class,
]);
Event::listen(TranscriptionFailed::class, NotifyTranscriptionFailed::class);
Event::listen(ExtractionCompleted::class, NotifyExtractionComplete::class);
Event::listen(ExtractionFailed::class, NotifyExtractionFailed::class);
Event::listen(MeetingFinalized::class, NotifyMeetingFinalized::class);
Event::listen(MeetingApproved::class, NotifyMeetingApproved::class);
```

### Event Dispatch Points

| Event | Dispatched From | Line |
|-------|----------------|------|
| `MeetingFinalized` | `MeetingService::finalize()` | After status change and version creation |
| `MeetingApproved` | `MeetingService::approve()` | After status change |

---

## Implementation Order

Features should be built in this order due to dependencies:

```
1.7 API Rate Limiting          (no dependencies, 2 days)
     ↓
1.8 Event Listeners            (no dependencies, 1 week)
     ↓
1.4 Email Notifications        (depends on 1.8 events, 2 weeks)
     ↓
1.3 Free Tier Enforcement      (no dependencies, 2 weeks)
     ↓
1.5 Global Search              (no dependencies, 2 weeks)
     ↓
1.6 Onboarding Wizard          (no dependencies, 1 week)
     ↓
1.1 Calendar Two-Way Sync      (most complex, 3 weeks)
```

**Total estimated effort: ~11 weeks**

**Rationale:**
- 1.7 (rate limiting) is trivial and should be done first as a quick win
- 1.8 (events) must precede 1.4 (notifications) since notifications depend on event listeners
- 1.4 (notifications) depends on the new events from 1.8
- 1.3, 1.5, 1.6 are independent and can be built in parallel if multiple developers are available
- 1.1 (calendar) is the most complex and benefits from all other features being stable first
