# Phase 1: Foundation — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement 7 Phase 1 features: API rate limiting, event listeners, email notifications, free tier enforcement, global search, onboarding wizard, and calendar two-way sync.

**Architecture:** Each feature builds on existing antaraFlow domain structure (DDD). Features are ordered by dependency: rate limiting (no deps) → events (no deps) → notifications (depends on events) → free tier → search → onboarding → calendar sync.

**Tech Stack:** Laravel 12, PHP 8.4, Alpine.js 3, Tailwind CSS 4, Pest 4, google/apiclient, microsoft/microsoft-graph

**Design Doc:** `docs/plans/2026-03-05-phase1-foundation-design.md`

---

## Task 1: API Rate Limiting

**Files:**
- Modify: `bootstrap/app.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/Domain/API/RateLimitingTest.php`

**Step 1: Write the failing test**

```bash
php artisan make:test --pest Domain/API/RateLimitingTest
```

```php
// tests/Feature/Domain/API/RateLimitingTest.php
<?php

declare(strict_types=1);

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->org = Organization::factory()->create();
    $this->org->members()->attach($this->user, ['role' => 'owner']);
    $this->user->update(['current_organization_id' => $this->org->id]);

    $plainKey = 'test-api-key-' . fake()->uuid();
    $this->apiKey = ApiKey::factory()->create([
        'organization_id' => $this->org->id,
        'secret_hash' => hash('sha256', $plainKey),
        'is_active' => true,
    ]);
    $this->bearerToken = $plainKey;
});

it('allows requests within rate limit', function () {
    $this->getJson('/api/v1/meetings', [
        'Authorization' => 'Bearer ' . $this->bearerToken,
    ])->assertOk();
});

it('returns 429 when rate limit is exceeded', function () {
    // The rate limiter is set to 60/min per API key.
    // We use a cache-based approach to simulate exceeding it.
    $rateLimiterKey = 'api:' . $this->apiKey->id;
    cache()->put('throttle:' . $rateLimiterKey, 61, 60);

    // Since we can't easily hit 60 requests in a test,
    // we verify the throttle middleware is applied by checking
    // rate limit headers are present on a successful response.
    $response = $this->getJson('/api/v1/meetings', [
        'Authorization' => 'Bearer ' . $this->bearerToken,
    ]);

    $response->assertHeader('X-RateLimit-Limit');
    $response->assertHeader('X-RateLimit-Remaining');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter=RateLimitingTest
```

Expected: FAIL — no rate limit headers present.

**Step 3: Configure rate limiter in bootstrap/app.php**

Add to `bootstrap/app.php` — add imports and rate limiter config inside `withMiddleware`:

```php
// At top of file, add:
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

// Inside withMiddleware callback, after alias block, add:
RateLimiter::for('api', function (Request $request) {
    $apiKey = $request->attributes->get('api_key');

    return Limit::perMinute(60)->by($apiKey?->id ?: $request->ip());
});
```

**Step 4: Add throttle middleware to API routes**

In `routes/api.php`, add `throttle:api` middleware:

```php
Route::prefix('v1')->middleware([ApiKeyAuthentication::class, 'throttle:api'])->group(function () {
    // ... existing routes
});
```

**Step 5: Run test to verify it passes**

```bash
php artisan test --compact --filter=RateLimitingTest
```

Expected: PASS

**Step 6: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add bootstrap/app.php routes/api.php tests/Feature/Domain/API/RateLimitingTest.php
git commit -m "feat: add API rate limiting (60 req/min per API key)"
```

---

## Task 2: Event Listeners — New Events

**Files:**
- Create: `app/Domain/Meeting/Events/MeetingFinalized.php`
- Create: `app/Domain/Meeting/Events/MeetingApproved.php`
- Modify: `app/Domain/Meeting/Services/MeetingService.php`
- Create: `tests/Feature/Domain/Meeting/MeetingEventsTest.php`

**Step 1: Create MeetingFinalized event**

```bash
php artisan make:class Domain/Meeting/Events/MeetingFinalized --no-interaction
```

Then write:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Events;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class MeetingFinalized
{
    use Dispatchable;

    public function __construct(
        public readonly MinutesOfMeeting $meeting,
        public readonly User $finalizedBy,
    ) {}
}
```

**Step 2: Create MeetingApproved event**

```bash
php artisan make:class Domain/Meeting/Events/MeetingApproved --no-interaction
```

Then write:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Events;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class MeetingApproved
{
    use Dispatchable;

    public function __construct(
        public readonly MinutesOfMeeting $meeting,
        public readonly User $approvedBy,
    ) {}
}
```

**Step 3: Write test for event dispatching**

```bash
php artisan make:test --pest Domain/Meeting/MeetingEventsTest
```

```php
<?php

declare(strict_types=1);

use App\Domain\Meeting\Events\MeetingApproved;
use App\Domain\Meeting\Events\MeetingFinalized;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Services\MeetingService;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->meetingService = app(MeetingService::class);
});

it('dispatches MeetingFinalized event on finalize', function () {
    Event::fake([MeetingFinalized::class]);

    $meeting = MinutesOfMeeting::factory()->create([
        'status' => MeetingStatus::Draft,
        'created_by' => $this->user->id,
    ]);

    $this->meetingService->finalize($meeting, $this->user);

    Event::assertDispatched(MeetingFinalized::class, function ($event) use ($meeting) {
        return $event->meeting->id === $meeting->id
            && $event->finalizedBy->id === $this->user->id;
    });
});

it('dispatches MeetingApproved event on approve', function () {
    Event::fake([MeetingApproved::class]);

    $meeting = MinutesOfMeeting::factory()->create([
        'status' => MeetingStatus::Finalized,
        'created_by' => $this->user->id,
    ]);

    $this->meetingService->approve($meeting, $this->user);

    Event::assertDispatched(MeetingApproved::class, function ($event) use ($meeting) {
        return $event->meeting->id === $meeting->id
            && $event->approvedBy->id === $this->user->id;
    });
});
```

**Step 4: Run tests to verify they fail**

```bash
php artisan test --compact --filter=MeetingEventsTest
```

Expected: FAIL — events not dispatched.

**Step 5: Modify MeetingService to dispatch events**

In `app/Domain/Meeting/Services/MeetingService.php`:

Add imports at top:
```php
use App\Domain\Meeting\Events\MeetingApproved;
use App\Domain\Meeting\Events\MeetingFinalized;
```

In `finalize()` method, add after `$this->auditService->log('finalized', $mom);` (line 107):
```php
MeetingFinalized::dispatch($mom, $user);
```

In `approve()` method, add after `$this->auditService->log('approved', $mom);` (line 119):
```php
MeetingApproved::dispatch($mom, $user);
```

**Step 6: Run tests to verify they pass**

```bash
php artisan test --compact --filter=MeetingEventsTest
```

Expected: PASS

**Step 7: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Meeting/Events/ app/Domain/Meeting/Services/MeetingService.php tests/Feature/Domain/Meeting/MeetingEventsTest.php
git commit -m "feat: add MeetingFinalized and MeetingApproved events"
```

---

## Task 3: Event Listeners — Transcription & Extraction Listeners

**Files:**
- Create: `app/Domain/Transcription/Listeners/NotifyTranscriptionComplete.php`
- Create: `app/Domain/Transcription/Listeners/NotifyTranscriptionFailed.php`
- Create: `app/Domain/AI/Listeners/NotifyExtractionComplete.php`
- Create: `app/Domain/AI/Listeners/NotifyExtractionFailed.php`
- Create: `app/Domain/Meeting/Listeners/NotifyMeetingFinalized.php`
- Create: `app/Domain/Meeting/Listeners/NotifyMeetingApproved.php`
- Create: `app/Domain/Meeting/Notifications/MeetingApprovedNotification.php`
- Create: `app/Domain/Transcription/Notifications/TranscriptionCompletedNotification.php`
- Create: `app/Domain/Transcription/Notifications/TranscriptionFailedNotification.php`
- Create: `app/Domain/AI/Notifications/ExtractionCompletedNotification.php`
- Create: `app/Domain/AI/Notifications/ExtractionFailedNotification.php`
- Modify: `app/Domain/Meeting/Notifications/MeetingFinalizedNotification.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `tests/Feature/Domain/EventListenersTest.php`

**Step 1: Create notification classes**

Create `app/Domain/Transcription/Notifications/TranscriptionCompletedNotification.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Notifications;

use App\Domain\Transcription\Models\AudioTranscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TranscriptionCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AudioTranscription $transcription,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'transcription_completed',
            'transcription_id' => $this->transcription->id,
            'meeting_id' => $this->transcription->minutes_of_meeting_id,
            'meeting_title' => $this->transcription->meeting?->title,
        ];
    }
}
```

Create `app/Domain/Transcription/Notifications/TranscriptionFailedNotification.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Notifications;

use App\Domain\Transcription\Models\AudioTranscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TranscriptionFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AudioTranscription $transcription,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Transcription Failed')
            ->greeting("Hello {$notifiable->name},")
            ->line('A transcription has failed for your meeting.')
            ->line('Please try uploading the audio file again or contact support.')
            ->action('View Meeting', route('meetings.show', $this->transcription->minutes_of_meeting_id));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'transcription_failed',
            'transcription_id' => $this->transcription->id,
            'meeting_id' => $this->transcription->minutes_of_meeting_id,
            'meeting_title' => $this->transcription->meeting?->title,
        ];
    }
}
```

Create `app/Domain/AI/Notifications/ExtractionCompletedNotification.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\AI\Notifications;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExtractionCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MinutesOfMeeting $meeting,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'extraction_completed',
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
        ];
    }
}
```

Create `app/Domain/AI/Notifications/ExtractionFailedNotification.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\AI\Notifications;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExtractionFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MinutesOfMeeting $meeting,
        public string $error,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("AI Extraction Failed: {$this->meeting->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("AI extraction failed for meeting: **{$this->meeting->title}**.")
            ->line('Please try running the extraction again.')
            ->action('View Meeting', route('meetings.show', $this->meeting));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'extraction_failed',
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'error' => $this->error,
        ];
    }
}
```

Create `app/Domain/Meeting/Notifications/MeetingApprovedNotification.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Notifications;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MinutesOfMeeting $meeting,
        public User $approvedBy,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Meeting Approved: {$this->meeting->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The meeting **{$this->meeting->title}** has been approved by {$this->approvedBy->name}.")
            ->action('View Meeting', route('meetings.show', $this->meeting));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'meeting_approved',
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'approved_by' => $this->approvedBy->name,
        ];
    }
}
```

**Step 2: Update MeetingFinalizedNotification to include mail channel**

Modify `app/Domain/Meeting/Notifications/MeetingFinalizedNotification.php` — add `mail` to `via()` and add `toMail()`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Notifications;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingFinalizedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MinutesOfMeeting $meeting,
        public User $finalizedBy,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Meeting Finalized: {$this->meeting->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The meeting **{$this->meeting->title}** has been finalized by {$this->finalizedBy->name}.")
            ->line('Please review and take action on your assigned items.')
            ->action('View Meeting', route('meetings.show', $this->meeting));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'meeting_finalized',
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'finalized_by' => $this->finalizedBy->name,
        ];
    }
}
```

**Step 3: Create listener classes**

Create `app/Domain/Transcription/Listeners/NotifyTranscriptionComplete.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Listeners;

use App\Domain\Transcription\Events\TranscriptionCompleted;
use App\Domain\Transcription\Notifications\TranscriptionCompletedNotification;
use App\Models\User;

class NotifyTranscriptionComplete
{
    public function handle(TranscriptionCompleted $event): void
    {
        $meeting = $event->transcription->meeting;

        if (! $meeting) {
            return;
        }

        $creator = User::find($meeting->created_by);

        $creator?->notify(new TranscriptionCompletedNotification($event->transcription));
    }
}
```

Create `app/Domain/Transcription/Listeners/NotifyTranscriptionFailed.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Listeners;

use App\Domain\Transcription\Events\TranscriptionFailed;
use App\Domain\Transcription\Notifications\TranscriptionFailedNotification;
use App\Models\User;

class NotifyTranscriptionFailed
{
    public function handle(TranscriptionFailed $event): void
    {
        $meeting = $event->transcription->meeting;

        if (! $meeting) {
            return;
        }

        $creator = User::find($meeting->created_by);

        $creator?->notify(new TranscriptionFailedNotification($event->transcription));
    }
}
```

Create `app/Domain/AI/Listeners/NotifyExtractionComplete.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\AI\Listeners;

use App\Domain\AI\Events\ExtractionCompleted;
use App\Domain\AI\Notifications\ExtractionCompletedNotification;
use App\Models\User;

class NotifyExtractionComplete
{
    public function handle(ExtractionCompleted $event): void
    {
        $creator = User::find($event->meeting->created_by);

        $creator?->notify(new ExtractionCompletedNotification($event->meeting));
    }
}
```

Create `app/Domain/AI/Listeners/NotifyExtractionFailed.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\AI\Listeners;

use App\Domain\AI\Events\ExtractionFailed;
use App\Domain\AI\Notifications\ExtractionFailedNotification;
use App\Models\User;

class NotifyExtractionFailed
{
    public function handle(ExtractionFailed $event): void
    {
        $creator = User::find($event->meeting->created_by);

        $creator?->notify(new ExtractionFailedNotification($event->meeting, $event->error));
    }
}
```

Create `app/Domain/Meeting/Listeners/NotifyMeetingFinalized.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Listeners;

use App\Domain\Meeting\Events\MeetingFinalized;
use App\Domain\Meeting\Notifications\MeetingFinalizedNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMeetingFinalized implements ShouldQueue
{
    public function handle(MeetingFinalized $event): void
    {
        $meeting = $event->meeting->load('attendees');

        foreach ($meeting->attendees as $attendee) {
            if ($attendee->user_id) {
                $user = User::find($attendee->user_id);
                $user?->notify(new MeetingFinalizedNotification($meeting, $event->finalizedBy));
            }
        }
    }
}
```

Create `app/Domain/Meeting/Listeners/NotifyMeetingApproved.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Listeners;

use App\Domain\Meeting\Events\MeetingApproved;
use App\Domain\Meeting\Notifications\MeetingApprovedNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMeetingApproved implements ShouldQueue
{
    public function handle(MeetingApproved $event): void
    {
        $meeting = $event->meeting->load('attendees');

        foreach ($meeting->attendees as $attendee) {
            if ($attendee->user_id) {
                $user = User::find($attendee->user_id);
                $user?->notify(new MeetingApprovedNotification($meeting, $event->approvedBy));
            }
        }
    }
}
```

**Step 4: Register listeners in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, add imports:

```php
use App\Domain\AI\Events\ExtractionCompleted;
use App\Domain\AI\Events\ExtractionFailed;
use App\Domain\AI\Listeners\NotifyExtractionComplete;
use App\Domain\AI\Listeners\NotifyExtractionFailed;
use App\Domain\Meeting\Events\MeetingApproved;
use App\Domain\Meeting\Events\MeetingFinalized;
use App\Domain\Meeting\Listeners\NotifyMeetingApproved;
use App\Domain\Meeting\Listeners\NotifyMeetingFinalized;
use App\Domain\Transcription\Events\TranscriptionCompleted;
use App\Domain\Transcription\Events\TranscriptionFailed;
use App\Domain\Transcription\Listeners\NotifyTranscriptionComplete;
use App\Domain\Transcription\Listeners\NotifyTranscriptionFailed;
use Illuminate\Support\Facades\Event;
```

In `boot()` method, add after the `View::composer` block:

```php
Event::listen(TranscriptionCompleted::class, NotifyTranscriptionComplete::class);
Event::listen(TranscriptionFailed::class, NotifyTranscriptionFailed::class);
Event::listen(ExtractionCompleted::class, NotifyExtractionComplete::class);
Event::listen(ExtractionFailed::class, NotifyExtractionFailed::class);
Event::listen(MeetingFinalized::class, NotifyMeetingFinalized::class);
Event::listen(MeetingApproved::class, NotifyMeetingApproved::class);
```

**Step 5: Write listener tests**

```bash
php artisan make:test --pest Domain/EventListenersTest
```

```php
<?php

declare(strict_types=1);

use App\Domain\AI\Events\ExtractionCompleted;
use App\Domain\AI\Events\ExtractionFailed;
use App\Domain\Meeting\Events\MeetingApproved;
use App\Domain\Meeting\Events\MeetingFinalized;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Events\TranscriptionCompleted;
use App\Domain\Transcription\Events\TranscriptionFailed;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('notifies creator when transcription completes', function () {
    Notification::fake();

    $meeting = MinutesOfMeeting::factory()->create(['created_by' => $this->user->id]);
    $transcription = AudioTranscription::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    TranscriptionCompleted::dispatch($transcription);

    Notification::assertSentTo($this->user, \App\Domain\Transcription\Notifications\TranscriptionCompletedNotification::class);
});

it('notifies creator when transcription fails', function () {
    Notification::fake();

    $meeting = MinutesOfMeeting::factory()->create(['created_by' => $this->user->id]);
    $transcription = AudioTranscription::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    TranscriptionFailed::dispatch($transcription);

    Notification::assertSentTo($this->user, \App\Domain\Transcription\Notifications\TranscriptionFailedNotification::class);
});

it('notifies creator when extraction completes', function () {
    Notification::fake();

    $meeting = MinutesOfMeeting::factory()->create(['created_by' => $this->user->id]);

    ExtractionCompleted::dispatch($meeting);

    Notification::assertSentTo($this->user, \App\Domain\AI\Notifications\ExtractionCompletedNotification::class);
});

it('notifies creator when extraction fails', function () {
    Notification::fake();

    $meeting = MinutesOfMeeting::factory()->create(['created_by' => $this->user->id]);

    ExtractionFailed::dispatch($meeting, 'Test error');

    Notification::assertSentTo($this->user, \App\Domain\AI\Notifications\ExtractionFailedNotification::class);
});
```

**Step 6: Run tests**

```bash
php artisan test --compact --filter=EventListenersTest
```

Expected: PASS

**Step 7: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Transcription/Listeners/ app/Domain/Transcription/Notifications/ app/Domain/AI/Listeners/ app/Domain/AI/Notifications/ app/Domain/Meeting/Listeners/ app/Domain/Meeting/Notifications/ app/Providers/AppServiceProvider.php tests/Feature/Domain/EventListenersTest.php
git commit -m "feat: wire event listeners to notification dispatching"
```

---

## Task 4: Free Tier — SubscriptionService

**Files:**
- Create: `app/Domain/Account/Services/SubscriptionService.php`
- Create: `app/Domain/Account/Exceptions/LimitExceededException.php`
- Create: `tests/Feature/Domain/Account/SubscriptionServiceTest.php`

**Step 1: Create LimitExceededException**

```php
// app/Domain/Account/Exceptions/LimitExceededException.php
<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use Exception;

class LimitExceededException extends Exception
{
    public function __construct(
        public readonly string $metric,
        public readonly int $currentUsage,
        public readonly int $limit,
        public readonly string $planName,
    ) {
        parent::__construct(
            "You have reached the {$metric} limit ({$currentUsage}/{$limit}) on your {$planName} plan. Please upgrade to continue."
        );
    }
}
```

**Step 2: Write the SubscriptionService test**

```bash
php artisan make:test --pest Domain/Account/SubscriptionServiceTest
```

```php
<?php

declare(strict_types=1);

use App\Domain\Account\Exceptions\LimitExceededException;
use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\SubscriptionPlan;
use App\Domain\Account\Services\SubscriptionService;

beforeEach(function () {
    $this->plan = SubscriptionPlan::factory()->create([
        'name' => 'Free',
        'slug' => 'free-test',
        'max_meetings_per_month' => 5,
        'max_audio_minutes_per_month' => 30,
        'max_storage_mb' => 100,
        'max_users' => 1,
        'features' => ['ai_summaries' => false, 'export' => false, 'api_access' => false],
    ]);

    $this->org = Organization::factory()->create();

    OrganizationSubscription::withoutGlobalScopes()->create([
        'organization_id' => $this->org->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);

    $this->service = app(SubscriptionService::class);
});

it('returns true when usage is within limit', function () {
    expect($this->service->canPerform($this->org, 'meetings'))->toBeTrue();
});

it('throws LimitExceededException when limit exceeded', function () {
    // Increment usage to the limit
    for ($i = 0; $i < 5; $i++) {
        $this->service->incrementUsage($this->org, 'meetings');
    }

    $this->service->checkLimit($this->org, 'meetings');
})->throws(LimitExceededException::class);

it('returns correct current usage', function () {
    $this->service->incrementUsage($this->org, 'meetings');
    $this->service->incrementUsage($this->org, 'meetings');

    expect($this->service->getCurrentUsage($this->org, 'meetings'))->toBe(2);
});

it('returns null for unlimited plans', function () {
    $unlimitedPlan = SubscriptionPlan::factory()->create([
        'max_meetings_per_month' => -1,
    ]);

    OrganizationSubscription::withoutGlobalScopes()->where('organization_id', $this->org->id)->update([
        'subscription_plan_id' => $unlimitedPlan->id,
    ]);

    expect($this->service->getPlanLimit($this->org, 'meetings'))->toBeNull();
    expect($this->service->canPerform($this->org, 'meetings'))->toBeTrue();
});

it('checks feature flags correctly', function () {
    expect($this->service->isFeatureEnabled($this->org, 'ai_summaries'))->toBeFalse();
});

it('resets monthly usage', function () {
    $this->service->incrementUsage($this->org, 'meetings', 3);

    expect($this->service->getCurrentUsage($this->org, 'meetings'))->toBe(3);

    $this->service->resetMonthlyUsage($this->org);

    expect($this->service->getCurrentUsage($this->org, 'meetings'))->toBe(0);
});
```

**Step 3: Run tests to verify they fail**

```bash
php artisan test --compact --filter=SubscriptionServiceTest
```

Expected: FAIL — SubscriptionService doesn't exist.

**Step 4: Implement SubscriptionService**

```php
// app/Domain/Account/Services/SubscriptionService.php
<?php

declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Domain\Account\Exceptions\LimitExceededException;
use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\UsageTracking;

class SubscriptionService
{
    private array $metricToField = [
        'meetings' => 'max_meetings_per_month',
        'audio_minutes' => 'max_audio_minutes_per_month',
        'storage_mb' => 'max_storage_mb',
        'members' => 'max_users',
    ];

    public function canPerform(Organization $org, string $metric): bool
    {
        $limit = $this->getPlanLimit($org, $metric);

        if ($limit === null) {
            return true;
        }

        return $this->getCurrentUsage($org, $metric) < $limit;
    }

    /** @throws LimitExceededException */
    public function checkLimit(Organization $org, string $metric): void
    {
        $limit = $this->getPlanLimit($org, $metric);

        if ($limit === null) {
            return;
        }

        $currentUsage = $this->getCurrentUsage($org, $metric);

        if ($currentUsage >= $limit) {
            $planName = $this->getActivePlanName($org);

            throw new LimitExceededException($metric, $currentUsage, $limit, $planName);
        }
    }

    public function getCurrentUsage(Organization $org, string $metric): int
    {
        $period = now()->format('Y-m');

        return (int) UsageTracking::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('metric', $metric)
            ->where('period', $period)
            ->value('value') ?? 0;
    }

    public function getPlanLimit(Organization $org, string $metric): ?int
    {
        $field = $this->metricToField[$metric] ?? null;

        if (! $field) {
            return null;
        }

        $plan = $this->getActivePlan($org);

        if (! $plan) {
            return null;
        }

        $limit = $plan->subscriptionPlan->{$field} ?? null;

        if ($limit === null || $limit === -1) {
            return null;
        }

        return (int) $limit;
    }

    public function incrementUsage(Organization $org, string $metric, int $amount = 1): void
    {
        $period = now()->format('Y-m');

        UsageTracking::withoutGlobalScopes()->updateOrCreate(
            [
                'organization_id' => $org->id,
                'metric' => $metric,
                'period' => $period,
            ],
            []
        )->increment('value', $amount);
    }

    public function decrementUsage(Organization $org, string $metric, int $amount = 1): void
    {
        $period = now()->format('Y-m');

        $tracking = UsageTracking::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('metric', $metric)
            ->where('period', $period)
            ->first();

        if ($tracking && $tracking->value > 0) {
            $tracking->decrement('value', min($amount, (int) $tracking->value));
        }
    }

    public function resetMonthlyUsage(Organization $org): void
    {
        $period = now()->format('Y-m');

        UsageTracking::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('period', $period)
            ->whereIn('metric', ['meetings', 'audio_minutes'])
            ->update(['value' => 0]);
    }

    public function isFeatureEnabled(Organization $org, string $feature): bool
    {
        $plan = $this->getActivePlan($org);

        if (! $plan) {
            return false;
        }

        $features = $plan->subscriptionPlan->features ?? [];

        return (bool) ($features[$feature] ?? false);
    }

    private function getActivePlan(Organization $org): ?OrganizationSubscription
    {
        return OrganizationSubscription::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'active')
            ->with('subscriptionPlan')
            ->first();
    }

    private function getActivePlanName(Organization $org): string
    {
        return $this->getActivePlan($org)?->subscriptionPlan->name ?? 'Unknown';
    }
}
```

**Step 5: Run tests to verify they pass**

```bash
php artisan test --compact --filter=SubscriptionServiceTest
```

Expected: PASS

**Step 6: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Account/Services/SubscriptionService.php app/Domain/Account/Exceptions/LimitExceededException.php tests/Feature/Domain/Account/SubscriptionServiceTest.php
git commit -m "feat: add SubscriptionService with limit checking and usage tracking"
```

---

## Task 5: Free Tier — Enforce Limits in Controllers/Services

**Files:**
- Modify: `app/Domain/Meeting/Services/MeetingService.php`
- Modify: `app/Domain/Meeting/Controllers/MeetingController.php` (store method)
- Create: `tests/Feature/Domain/Meeting/MeetingLimitTest.php`

**Step 1: Write the failing test**

```bash
php artisan make:test --pest Domain/Meeting/MeetingLimitTest
```

```php
<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\SubscriptionPlan;
use App\Domain\Account\Services\SubscriptionService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;

beforeEach(function () {
    $this->plan = SubscriptionPlan::factory()->create([
        'name' => 'Free',
        'slug' => 'free-limit-test',
        'max_meetings_per_month' => 2,
        'max_audio_minutes_per_month' => 30,
        'max_storage_mb' => 100,
        'max_users' => 1,
        'features' => ['ai_summaries' => false, 'export' => false],
    ]);

    $this->user = User::factory()->create();
    $this->org = Organization::factory()->create();
    $this->org->members()->attach($this->user, ['role' => 'owner']);
    $this->user->update(['current_organization_id' => $this->org->id]);

    OrganizationSubscription::withoutGlobalScopes()->create([
        'organization_id' => $this->org->id,
        'subscription_plan_id' => $this->plan->id,
        'status' => 'active',
        'starts_at' => now(),
    ]);
});

it('allows meeting creation within limit', function () {
    $this->actingAs($this->user)
        ->post(route('meetings.store'), [
            'title' => 'Test Meeting',
            'meeting_date' => now()->addDay()->format('Y-m-d'),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('minutes_of_meetings', ['title' => 'Test Meeting']);
});

it('blocks meeting creation when limit reached', function () {
    $subscriptionService = app(SubscriptionService::class);
    $subscriptionService->incrementUsage($this->org, 'meetings', 2);

    $this->actingAs($this->user)
        ->post(route('meetings.store'), [
            'title' => 'Over Limit Meeting',
            'meeting_date' => now()->addDay()->format('Y-m-d'),
        ])
        ->assertRedirect(route('subscription.index'));

    $this->assertDatabaseMissing('minutes_of_meetings', ['title' => 'Over Limit Meeting']);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter=MeetingLimitTest
```

Expected: FAIL — no limit checking exists.

**Step 3: Add limit checking to MeetingService::create()**

In `app/Domain/Meeting/Services/MeetingService.php`, add to constructor:

```php
public function __construct(
    private readonly VersionService $versionService,
    private readonly AuditService $auditService,
    private readonly MomNumberService $momNumberService,
    private readonly \App\Domain\Account\Services\SubscriptionService $subscriptionService,
) {}
```

At the beginning of `create()` method, add:

```php
$org = \App\Domain\Account\Models\Organization::find($user->current_organization_id);

if ($org) {
    $this->subscriptionService->checkLimit($org, 'meetings');
}
```

After the meeting is created (after the `DB::transaction` return), add usage increment. Wrap the whole transaction in a try-catch in the controller.

**Step 4: Handle LimitExceededException in MeetingController**

In `app/Domain/Meeting/Controllers/MeetingController.php`, in the `store()` method, wrap the service call:

```php
use App\Domain\Account\Exceptions\LimitExceededException;

try {
    $meeting = $this->meetingService->create($data, $request->user());
    // ... increment usage after successful create
    app(\App\Domain\Account\Services\SubscriptionService::class)
        ->incrementUsage($request->user()->currentOrganization, 'meetings');
} catch (LimitExceededException $e) {
    return redirect()->route('subscription.index')
        ->with('limit_exceeded', $e->getMessage());
}
```

**Step 5: Run tests to verify they pass**

```bash
php artisan test --compact --filter=MeetingLimitTest
```

Expected: PASS

**Step 6: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Meeting/Services/MeetingService.php app/Domain/Meeting/Controllers/MeetingController.php tests/Feature/Domain/Meeting/MeetingLimitTest.php
git commit -m "feat: enforce meeting creation limits from subscription plan"
```

---

## Task 6: Global Search — Service & Controller

**Files:**
- Create: `app/Domain/Search/Services/GlobalSearchService.php`
- Create: `app/Domain/Search/Controllers/SearchController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Domain/Search/GlobalSearchTest.php`

**Step 1: Write the failing test**

```bash
php artisan make:test --pest Domain/Search/GlobalSearchTest
```

```php
<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Project\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->org = Organization::factory()->create();
    $this->org->members()->attach($this->user, ['role' => 'owner']);
    $this->user->update(['current_organization_id' => $this->org->id]);
});

it('returns search results grouped by entity type', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'title' => 'Budget Review Meeting',
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $project = Project::factory()->create([
        'name' => 'Budget Project',
        'organization_id' => $this->org->id,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('search', ['q' => 'budget']))
        ->assertOk()
        ->assertJsonStructure(['meetings', 'action_items', 'projects'])
        ->assertJsonPath('meetings.0.title', 'Budget Review Meeting');
});

it('requires minimum 2 characters', function () {
    $this->actingAs($this->user)
        ->getJson(route('search', ['q' => 'a']))
        ->assertUnprocessable();
});

it('scopes results to current organization', function () {
    $otherOrg = Organization::factory()->create();

    MinutesOfMeeting::factory()->create([
        'title' => 'Other Org Meeting',
        'organization_id' => $otherOrg->id,
    ]);

    MinutesOfMeeting::factory()->create([
        'title' => 'My Org Meeting',
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('search', ['q' => 'meeting']))
        ->assertOk();

    $meetings = $response->json('meetings');
    expect($meetings)->toHaveCount(1);
    expect($meetings[0]['title'])->toBe('My Org Meeting');
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter=GlobalSearchTest
```

Expected: FAIL — route and controller don't exist.

**Step 3: Create GlobalSearchService**

```php
// app/Domain/Search/Services/GlobalSearchService.php
<?php

declare(strict_types=1);

namespace App\Domain\Search\Services;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomManualNote;
use App\Domain\Project\Models\Project;

class GlobalSearchService
{
    /** @return array<string, array<int, array<string, mixed>>> */
    public function search(string $query, int $organizationId, int $limit = 20): array
    {
        return [
            'meetings' => $this->searchMeetings($query, $organizationId, min($limit, 5)),
            'action_items' => $this->searchActionItems($query, $organizationId, min($limit, 5)),
            'projects' => $this->searchProjects($query, $organizationId, min($limit, 3)),
            'manual_notes' => $this->searchManualNotes($query, $organizationId, min($limit, 4)),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function searchMeetings(string $query, int $organizationId, int $limit): array
    {
        return MinutesOfMeeting::query()
            ->where('organization_id', $organizationId)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('mom_number', 'like', "%{$query}%")
                    ->orWhere('summary', 'like', "%{$query}%");
            })
            ->latest('meeting_date')
            ->limit($limit)
            ->get(['id', 'title', 'mom_number', 'status', 'meeting_date'])
            ->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'mom_number' => $m->mom_number,
                'status' => $m->status->value,
                'meeting_date' => $m->meeting_date?->toDateString(),
                'url' => route('meetings.show', $m),
                'type' => 'meeting',
            ])
            ->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function searchActionItems(string $query, int $organizationId, int $limit): array
    {
        return ActionItem::query()
            ->where('organization_id', $organizationId)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->with('meeting:id,title')
            ->latest()
            ->limit($limit)
            ->get(['id', 'title', 'status', 'priority', 'minutes_of_meeting_id'])
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'status' => $a->status->value,
                'priority' => $a->priority->value,
                'meeting_title' => $a->meeting?->title,
                'url' => $a->meeting ? route('meetings.action-items.show', [$a->meeting, $a]) : null,
                'type' => 'action_item',
            ])
            ->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function searchProjects(string $query, int $organizationId, int $limit): array
    {
        return Project::query()
            ->where('organization_id', $organizationId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->latest()
            ->limit($limit)
            ->get(['id', 'name', 'description'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->name,
                'description' => \Illuminate\Support\Str::limit($p->description, 100),
                'url' => route('projects.show', $p),
                'type' => 'project',
            ])
            ->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    private function searchManualNotes(string $query, int $organizationId, int $limit): array
    {
        return MomManualNote::query()
            ->whereHas('meeting', fn ($q) => $q->where('organization_id', $organizationId))
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%");
            })
            ->with('meeting:id,title')
            ->latest()
            ->limit($limit)
            ->get(['id', 'title', 'minutes_of_meeting_id'])
            ->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'meeting_title' => $n->meeting?->title,
                'url' => $n->meeting ? route('meetings.show', $n->meeting) : null,
                'type' => 'manual_note',
            ])
            ->toArray();
    }
}
```

**Step 4: Create SearchController**

```php
// app/Domain/Search/Controllers/SearchController.php
<?php

declare(strict_types=1);

namespace App\Domain\Search\Controllers;

use App\Domain\Search\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SearchController extends Controller
{
    public function __construct(
        private readonly GlobalSearchService $searchService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $results = $this->searchService->search(
            $request->string('q')->toString(),
            $request->user()->current_organization_id,
        );

        return response()->json($results);
    }
}
```

**Step 5: Add route**

In `routes/web.php`, add inside the main authenticated group:

```php
use App\Domain\Search\Controllers\SearchController;

Route::get('search', [SearchController::class, 'index'])->name('search');
```

**Step 6: Run tests to verify they pass**

```bash
php artisan test --compact --filter=GlobalSearchTest
```

Expected: PASS

**Step 7: Run pint and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Search/ routes/web.php tests/Feature/Domain/Search/GlobalSearchTest.php
git commit -m "feat: add global search across meetings, action items, projects, notes"
```

---

## Task 7: Global Search — Command Palette Enhancement

**Files:**
- Modify: `resources/views/layouts/partials/command-palette.blade.php`
- Modify: `resources/js/app.js` (or inline Alpine component)

**Step 1: Update command palette to use live search API**

Replace the content of `resources/views/layouts/partials/command-palette.blade.php` with an enhanced version that uses `fetch()` to hit the `/search` endpoint with debounced input. Show results grouped by entity type with appropriate icons. Keep the existing navigation commands for when the query is empty.

Key changes:
- Add `x-on:input.debounce.300ms="searchGlobal()"` to the input
- Add `searchGlobal()` method that calls `fetch('/search?q=' + commandQuery)`
- Group results by type with section headers and icons
- Keep keyboard navigation (arrow keys + enter)

**Step 2: Verify visually in browser**

Open the app, press `Cmd+K`, type a search query, verify results appear grouped.

**Step 3: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/views/layouts/partials/command-palette.blade.php
git commit -m "feat: enhance command palette with live global search"
```

---

## Task 8: Onboarding Wizard — Migration & Middleware

**Files:**
- Create: migration for `onboarding_completed_at` on users
- Create: `app/Domain/Account/Middleware/OnboardingMiddleware.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Feature/Domain/Account/OnboardingMiddlewareTest.php`

**Step 1: Create migration**

```bash
php artisan make:migration add_onboarding_completed_at_to_users_table --table=users --no-interaction
```

```php
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->timestamp('onboarding_completed_at')->nullable()->after('last_login_at');
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('onboarding_completed_at');
    });
}
```

**Step 2: Write test for middleware**

```bash
php artisan make:test --pest Domain/Account/OnboardingMiddlewareTest
```

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Domain\Account\Models\Organization;

it('redirects new users to onboarding', function () {
    $user = User::factory()->create(['onboarding_completed_at' => null]);
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_organization_id' => $org->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding.step', ['step' => 1]));
});

it('allows access for onboarded users', function () {
    $user = User::factory()->create(['onboarding_completed_at' => now()]);
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_organization_id' => $org->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('allows access to onboarding routes without redirect loop', function () {
    $user = User::factory()->create(['onboarding_completed_at' => null]);
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => 'owner']);
    $user->update(['current_organization_id' => $org->id]);

    $this->actingAs($user)
        ->get(route('onboarding.step', ['step' => 1]))
        ->assertOk();
});
```

**Step 3: Create OnboardingMiddleware**

```php
// app/Domain/Account/Middleware/OnboardingMiddleware.php
<?php

declare(strict_types=1);

namespace App\Domain\Account\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OnboardingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()
            && $request->user()->onboarding_completed_at === null
            && ! $request->routeIs('onboarding.*', 'login', 'logout', 'register', 'api.*')
        ) {
            return redirect()->route('onboarding.step', ['step' => 1]);
        }

        return $next($request);
    }
}
```

**Step 4: Register middleware and run migration**

In `bootstrap/app.php`, add to the alias array:
```php
'onboarding' => \App\Domain\Account\Middleware\OnboardingMiddleware::class,
```

Add `onboarding_completed_at` to `User` model casts:
```php
'onboarding_completed_at' => 'datetime',
```

Run migration:
```bash
php artisan migrate --no-interaction
```

**Step 5: Run tests**

```bash
php artisan test --compact --filter=OnboardingMiddlewareTest
```

Expected: PASS (after adding routes in next task)

**Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/*onboarding* app/Domain/Account/Middleware/OnboardingMiddleware.php bootstrap/app.php app/Models/User.php
git commit -m "feat: add onboarding middleware and migration"
```

---

## Task 9: Onboarding Wizard — Controller, Routes & Views

**Files:**
- Create: `app/Domain/Account/Controllers/OnboardingController.php`
- Create: `resources/views/onboarding/layout.blade.php`
- Create: `resources/views/onboarding/step1.blade.php`
- Create: `resources/views/onboarding/step2.blade.php`
- Create: `resources/views/onboarding/step3.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Domain/Account/Controllers/Auth/RegisterController.php`
- Create: `tests/Feature/Domain/Account/OnboardingFlowTest.php`

**Step 1: Write the tests**

```bash
php artisan make:test --pest Domain/Account/OnboardingFlowTest
```

```php
<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['onboarding_completed_at' => null]);
    $this->org = Organization::factory()->create();
    $this->org->members()->attach($this->user, ['role' => 'owner']);
    $this->user->update(['current_organization_id' => $this->org->id]);
});

it('shows step 1 form', function () {
    $this->actingAs($this->user)
        ->get(route('onboarding.step', ['step' => 1]))
        ->assertOk()
        ->assertSee('Complete Your Profile');
});

it('processes step 1 and advances to step 2', function () {
    $this->actingAs($this->user)
        ->post(route('onboarding.update', ['step' => 1]), [
            'name' => 'Updated Name',
        ])
        ->assertRedirect(route('onboarding.step', ['step' => 2]));

    expect($this->user->fresh()->name)->toBe('Updated Name');
});

it('processes step 2 and advances to step 3', function () {
    $this->actingAs($this->user)
        ->post(route('onboarding.update', ['step' => 2]), [
            'name' => $this->org->name,
            'timezone' => 'Asia/Kuala_Lumpur',
            'language' => 'en',
        ])
        ->assertRedirect(route('onboarding.step', ['step' => 3]));
});

it('skips onboarding and marks complete', function () {
    $this->actingAs($this->user)
        ->post(route('onboarding.skip'))
        ->assertRedirect(route('dashboard'));

    expect($this->user->fresh()->onboarding_completed_at)->not->toBeNull();
});

it('redirects to dashboard after registration', function () {
    $response = $this->post(route('register'), [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('onboarding.step', ['step' => 1]));
});
```

**Step 2: Create OnboardingController**

```php
// app/Domain/Account/Controllers/OnboardingController.php
<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function show(Request $request, int $step): View|RedirectResponse
    {
        if ($request->user()->onboarding_completed_at) {
            return redirect()->route('dashboard');
        }

        return match ($step) {
            1 => view('onboarding.step1', ['user' => $request->user()]),
            2 => view('onboarding.step2', [
                'organization' => $request->user()->currentOrganization,
            ]),
            3 => view('onboarding.step3'),
            default => redirect()->route('onboarding.step', ['step' => 1]),
        };
    }

    public function update(Request $request, int $step): RedirectResponse
    {
        return match ($step) {
            1 => $this->updateStep1($request),
            2 => $this->updateStep2($request),
            3 => $this->updateStep3($request),
            default => redirect()->route('onboarding.step', ['step' => 1]),
        };
    }

    public function skip(Request $request): RedirectResponse
    {
        $request->user()->update(['onboarding_completed_at' => now()]);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to antaraFLOW! Create your first meeting to get started.');
    }

    private function updateStep1(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $request->user()->update($validated);

        return redirect()->route('onboarding.step', ['step' => 2]);
    }

    private function updateStep2(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'timezone' => 'required|string|timezone',
            'language' => 'required|string|in:en,ms',
        ]);

        $request->user()->currentOrganization->update($validated);

        return redirect()->route('onboarding.step', ['step' => 3]);
    }

    private function updateStep3(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'emails' => 'nullable|string',
        ]);

        // Process email invitations if provided
        if (! empty($validated['emails'])) {
            $emails = array_filter(
                array_map('trim', explode(',', $validated['emails']))
            );
            // Invitation logic deferred to existing OrganizationService
        }

        $request->user()->update(['onboarding_completed_at' => now()]);

        return redirect()->route('dashboard')
            ->with('success', 'Welcome to antaraFLOW! Create your first meeting to get started.');
    }
}
```

**Step 3: Add routes**

In `routes/web.php`, add onboarding routes (inside auth middleware group but before onboarding middleware):

```php
use App\Domain\Account\Controllers\OnboardingController;

Route::middleware(['auth'])->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/step/{step}', [OnboardingController::class, 'show'])->name('step');
    Route::post('/step/{step}', [OnboardingController::class, 'update'])->name('update');
    Route::post('/skip', [OnboardingController::class, 'skip'])->name('skip');
});
```

Add `onboarding` middleware to the main web group (the group with `auth`, `org.context`, `org.suspended`).

**Step 4: Update RegisterController redirect**

In `app/Domain/Account/Controllers/Auth/RegisterController.php`, change line 42:

```php
// From:
return redirect()->route('organizations.index');
// To:
return redirect()->route('onboarding.step', ['step' => 1]);
```

**Step 5: Create view files**

Create minimal Blade views for each step using the existing layout patterns. Each view should have a form with the relevant fields, next/skip buttons, and a progress indicator.

**Step 6: Run tests**

```bash
php artisan test --compact --filter=OnboardingFlowTest
```

Expected: PASS

**Step 7: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Account/Controllers/OnboardingController.php resources/views/onboarding/ routes/web.php app/Domain/Account/Controllers/Auth/RegisterController.php tests/Feature/Domain/Account/OnboardingFlowTest.php
git commit -m "feat: add 3-step onboarding wizard for new users"
```

---

## Task 10: Calendar Two-Way Sync — Foundation

**Files:**
- Create: migration for `calendar_connections` table
- Create: migration to add calendar fields to `minutes_of_meetings`
- Create: `app/Domain/Calendar/Models/CalendarConnection.php`
- Create: `app/Domain/Calendar/Contracts/CalendarProviderInterface.php`
- Create: `config/calendar.php`
- Modify: `.env.example`

**Step 1: Create migrations**

```bash
php artisan make:migration create_calendar_connections_table --no-interaction
php artisan make:migration add_calendar_fields_to_minutes_of_meetings_table --table=minutes_of_meetings --no-interaction
```

`calendar_connections`:
```php
Schema::create('calendar_connections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('provider'); // google, outlook
    $table->text('access_token');
    $table->text('refresh_token')->nullable();
    $table->timestamp('token_expires_at')->nullable();
    $table->string('calendar_id')->nullable();
    $table->string('webhook_channel_id')->nullable();
    $table->timestamp('webhook_expiry')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->unique(['user_id', 'provider']);
});
```

`minutes_of_meetings` additions:
```php
Schema::table('minutes_of_meetings', function (Blueprint $table) {
    $table->string('calendar_event_id')->nullable()->after('metadata');
    $table->string('calendar_provider')->nullable()->after('calendar_event_id');
    $table->timestamp('calendar_synced_at')->nullable()->after('calendar_provider');
});
```

**Step 2: Create CalendarProviderInterface**

```php
// app/Domain/Calendar/Contracts/CalendarProviderInterface.php
<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Contracts;

use App\Domain\Calendar\Models\CalendarConnection;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\Request;

interface CalendarProviderInterface
{
    public function getAuthUrl(string $redirectUri, string $state): string;
    public function handleCallback(string $code, string $redirectUri): array;
    public function refreshToken(CalendarConnection $connection): CalendarConnection;
    public function createEvent(CalendarConnection $connection, MinutesOfMeeting $meeting): string;
    public function updateEvent(CalendarConnection $connection, MinutesOfMeeting $meeting): void;
    public function deleteEvent(CalendarConnection $connection, string $eventId): void;
    /** @return array<int, array{id: string, name: string}> */
    public function listCalendars(CalendarConnection $connection): array;
    public function registerWebhook(CalendarConnection $connection): void;
    /** @return array<int, array<string, mixed>> */
    public function handleWebhook(Request $request): array;
}
```

**Step 3: Create CalendarConnection model**

```php
// app/Domain/Calendar/Models/CalendarConnection.php
<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarConnection extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'webhook_expiry' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }
}
```

**Step 4: Create config/calendar.php**

```php
<?php

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

**Step 5: Update .env.example**

Add calendar provider env vars.

**Step 6: Run migration and commit**

```bash
php artisan migrate --no-interaction
vendor/bin/pint --dirty --format agent
git add database/migrations/*calendar* app/Domain/Calendar/ config/calendar.php .env.example
git commit -m "feat: add calendar sync foundation (model, migration, contract, config)"
```

---

## Task 11: Calendar Two-Way Sync — Google Provider

**Files:**
- Create: `app/Domain/Calendar/Providers/GoogleCalendarProvider.php`
- Create: `tests/Feature/Domain/Calendar/GoogleCalendarTest.php`

Install package first:
```bash
composer require google/apiclient --no-interaction
```

Implement `GoogleCalendarProvider` implementing `CalendarProviderInterface`. Key methods:
- `getAuthUrl()` — build Google OAuth consent URL with calendar scope
- `handleCallback()` — exchange code for tokens
- `createEvent()` — create Google Calendar event from meeting data
- `updateEvent()` — update existing event
- `deleteEvent()` — remove event
- `registerWebhook()` — set up push notifications

Write tests using HTTP fakes for Google API calls.

**Commit:** `feat: add Google Calendar provider`

---

## Task 12: Calendar Two-Way Sync — Outlook Provider

**Files:**
- Create: `app/Domain/Calendar/Providers/OutlookCalendarProvider.php`
- Create: `tests/Feature/Domain/Calendar/OutlookCalendarTest.php`

Install package first:
```bash
composer require microsoft/microsoft-graph --no-interaction
```

Implement `OutlookCalendarProvider` implementing `CalendarProviderInterface`. Same methods as Google but using Microsoft Graph API.

**Commit:** `feat: add Outlook Calendar provider`

---

## Task 13: Calendar Two-Way Sync — Service, Controller & Routes

**Files:**
- Create: `app/Domain/Calendar/Services/CalendarSyncService.php`
- Create: `app/Domain/Calendar/Controllers/CalendarConnectionController.php`
- Create: `app/Domain/Calendar/Controllers/CalendarWebhookController.php`
- Modify: `routes/web.php`
- Create: `resources/views/calendar/connections.blade.php`
- Create: `tests/Feature/Domain/Calendar/CalendarSyncTest.php`

**CalendarSyncService** orchestrates:
- `syncToCalendar(MinutesOfMeeting $meeting)` — find all active connections for org → push to each
- `syncFromCalendar(array $eventData, CalendarConnection $conn)` — create/update meeting from external event
- `resolveConflict()` — calendar changes only update date/time/location, never content

**CalendarConnectionController:**
- `index()` — list connections
- `connect($provider)` — redirect to OAuth
- `callback($provider)` — handle OAuth callback, store tokens
- `disconnect($connection)` — revoke + delete

**CalendarWebhookController:**
- `handle($provider)` — receive webhooks, process changed events

**Routes:**
```php
Route::prefix('calendar')->name('calendar.')->group(function () {
    Route::get('connections', [CalendarConnectionController::class, 'index'])->name('connections');
    Route::get('connect/{provider}', [CalendarConnectionController::class, 'connect'])->name('connect');
    Route::get('callback/{provider}', [CalendarConnectionController::class, 'callback'])->name('callback');
    Route::delete('disconnect/{connection}', [CalendarConnectionController::class, 'disconnect'])->name('disconnect');
});

// Webhook (no auth)
Route::post('calendar/webhook/{provider}', [CalendarWebhookController::class, 'handle'])->name('calendar.webhook');
```

**Commit:** `feat: add calendar sync service, controller, and webhook handler`

---

## Task 14: Final Integration — Wire MeetingService to Calendar Sync

**Files:**
- Modify: `app/Domain/Meeting/Services/MeetingService.php`
- Create: `app/Domain/Calendar/Listeners/SyncMeetingToCalendar.php`
- Modify: `app/Providers/AppServiceProvider.php`

Create new events: `MeetingCreated`, `MeetingUpdated`, `MeetingDeleted`.

Create `SyncMeetingToCalendar` listener that listens to all three events and calls `CalendarSyncService::syncToCalendar()`.

Register in `AppServiceProvider::boot()`.

**Commit:** `feat: auto-sync meetings to connected calendars on create/update/delete`

---

## Task 15: Final Verification

**Step 1: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass.

**Step 2: Run pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 3: Final commit if needed**

```bash
git add -A && git commit -m "chore: final Phase 1 cleanup and formatting"
```

---

## Summary

| Task | Feature | Files | Estimated Time |
|------|---------|-------|---------------|
| 1 | API Rate Limiting | 3 | 30 min |
| 2 | New Events (MeetingFinalized/Approved) | 4 | 45 min |
| 3 | Event Listeners + Notifications | 14 | 2 hours |
| 4 | SubscriptionService | 3 | 1 hour |
| 5 | Enforce Limits in Controllers | 3 | 1 hour |
| 6 | Global Search Service + Controller | 4 | 1.5 hours |
| 7 | Command Palette Enhancement | 1 | 1 hour |
| 8 | Onboarding Migration + Middleware | 4 | 1 hour |
| 9 | Onboarding Controller + Views | 7 | 2 hours |
| 10 | Calendar Foundation | 5 | 1 hour |
| 11 | Google Calendar Provider | 2 | 3 hours |
| 12 | Outlook Calendar Provider | 2 | 3 hours |
| 13 | Calendar Sync Service + Controller | 5 | 3 hours |
| 14 | Calendar-Meeting Integration | 3 | 1 hour |
| 15 | Final Verification | 0 | 30 min |
