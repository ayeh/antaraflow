# MOM Confirmation Loop Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Turn MOM sharing from one-way distribution into a confirmation loop — a finalized MOM is circulated to individually-tokenised attendees who confirm or raise anchored amendments, and silence past a deadline confirms it while an objection holds it.

**Architecture:** Two new tables model a circulation *round* (`mom_circulations`) and its per-person recipients (`mom_circulation_recipients`, each with its own token). A new `MeetingStatus::PendingConfirmation` sits between `Finalized` and `Approved`. Guest remarks reuse the existing polymorphic `Comment` model once `user_id` is made nullable. An hourly job evaluates hold conditions and auto-approves.

**Tech Stack:** Laravel 12, PHP 8.4, Pest 4, Tailwind, Alpine, DomPDF (`barryvdh/laravel-dompdf`), MySQL on production / SQLite in tests.

**Design doc:** `docs/plans/2026-08-04-mom-confirmation-loop-design.md` (commit `f65f3d9`)

---

## Before you start

**Do not use a git worktree for this.** `.claude/worktrees` checkouts lack `vendor/`, `.env` and `node_modules`, and symlinking `vendor` breaks autoload. Work on a feature branch in the main checkout:

```bash
git checkout -b feat/mom-confirmation-loop
```

**The test baseline is not green.** `main` carries roughly 57 pre-existing failures. Never gate on "all tests pass". Capture the count first and gate on *"the failure count did not increase"*:

```bash
php artisan test --compact 2>&1 | tail -5
```

Write that number down. Compare against it after every phase.

**Run Pint before every commit** — the project requires it:

```bash
vendor/bin/pint --dirty --format agent
```

### Three traps that land squarely on this feature

Each one has already bitten this project. Read these before writing any code.

1. **`OrganizationScope` resolves to `1=0` for unauthenticated requests.** The guest confirmation route has no logged-in user, so every tenant-scoped query returns empty. Every query on the guest path needs `withoutGlobalScopes()` — copy the pattern from `app/Domain/Meeting/Controllers/GuestAccessController.php:63`.

2. **`organization_id` is not mass-assignable.** `Model::create()` drops it silently and the auth hook substitutes the *actor's* org — which is NULL inside a queued job. Always use `Model::createForOrganization($orgId, [...])` (`app/Support/Traits/BelongsToOrganization.php:37`), taking the org from the meeting rather than from the actor.

3. **A bare `$table->timestamp()` acquires `ON UPDATE CURRENT_TIMESTAMP` on production MySQL.** It would silently rewrite `responded_at` and `deemed_confirmed_at` every time the row is touched, corrupting the exact audit data this feature exists to produce — and SQLite in tests cannot see it. **This plan uses `$table->dateTime()` for every audit-bearing timestamp**, because DATETIME never acquires that behaviour implicitly. Do not "fix" them back to `timestamp()`.

### Prerequisite check — do this first, before Task 1

If `schedule:run` does not run on production, the deadline job never fires, minutes never get approved, and the whole feature is silently dead. Verify before building on top of it:

```bash
ssh into the prod box, then from /home/note/antaraFlow:
/usr/local/php84/bin/php artisan schedule:list
crontab -l | grep schedule:run
```

If no cron entry exists, stop and report it. The feature cannot ship without one.

---

## Phase 1 — Data model

### Task 1: Add the `PendingConfirmation` status

**Files:**
- Modify: `app/Support/Enums/MeetingStatus.php`
- Test: `tests/Unit/Domain/Meeting/MeetingStatusTest.php` (create)

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Support\Enums\MeetingStatus;

test('pending confirmation sits between finalized and approved', function () {
    expect(MeetingStatus::PendingConfirmation->value)->toBe('pending_confirmation');
    expect(MeetingStatus::tryFrom('pending_confirmation'))->toBe(MeetingStatus::PendingConfirmation);
});
```

**Step 2: Run it and confirm it fails**

```bash
php artisan test --compact --filter="pending confirmation sits"
```

Expected: FAIL — undefined constant `MeetingStatus::PendingConfirmation`.

**Step 3: Add the case**

In `app/Support/Enums/MeetingStatus.php`, add between `Finalized` and `Approved`:

```php
    case PendingConfirmation = 'pending_confirmation';
```

**Step 4: Verify it passes**

```bash
php artisan test --compact --filter="pending confirmation sits"
```

**Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Enums/MeetingStatus.php tests/Unit/Domain/Meeting/MeetingStatusTest.php
git commit -m "feat(mom): add PendingConfirmation meeting status"
```

---

### Task 2: `mom_circulations` table and model

**Files:**
- Create: `database/migrations/2026_08_04_100000_create_mom_circulations_table.php`
- Create: `app/Domain/Collaboration/Models/MomCirculation.php`
- Create: `database/factories/MomCirculationFactory.php`
- Test: `tests/Feature/Domain/Collaboration/MomCirculationTest.php`

**Step 1: Generate the migration**

```bash
php artisan make:migration create_mom_circulations_table --no-interaction
```

Rename the generated file to `2026_08_04_100000_create_mom_circulations_table.php` so ordering is deterministic.

**Step 2: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mom_circulations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sent_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('round')->default(1);
            $table->string('subject');
            $table->text('body_note')->nullable();

            // dateTime, not timestamp: a TIMESTAMP column can silently acquire
            // ON UPDATE CURRENT_TIMESTAMP on MySQL and rewrite itself whenever the
            // row is touched, which would corrupt the audit record this table exists for.
            $table->dateTime('deadline_at')->nullable();
            $table->dateTime('closed_at')->nullable();

            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->index(['status', 'deadline_at']);
            $table->index(['minutes_of_meeting_id', 'round']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mom_circulations');
    }
};
```

**Step 3: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Models\MomCirculation;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->owner = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->owner->id,
    ]);
});

test('a circulation belongs to a meeting and records who sent it', function () {
    $circulation = MomCirculation::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $this->meeting->id,
        'sent_by' => $this->owner->id,
        'subject' => 'Please confirm',
        'deadline_at' => now()->addDays(3),
    ]);

    expect($circulation->organization_id)->toBe($this->org->id)
        ->and($circulation->round)->toBe(1)
        ->and($circulation->status)->toBe('open')
        ->and($circulation->meeting->id)->toBe($this->meeting->id);
});

test('the open scope only returns circulations still accepting responses', function () {
    MomCirculation::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $this->meeting->id,
        'sent_by' => $this->owner->id,
        'subject' => 'Open one',
        'deadline_at' => now()->addDay(),
    ]);

    $closed = MomCirculation::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $this->meeting->id,
        'sent_by' => $this->owner->id,
        'subject' => 'Closed one',
        'deadline_at' => now()->addDay(),
    ]);
    $closed->update(['status' => 'closed_approved']);

    expect(MomCirculation::open()->count())->toBe(1);
});
```

**Step 4: Run it and confirm it fails**

```bash
php artisan test --compact --filter=MomCirculationTest
```

Expected: FAIL — class `MomCirculation` not found.

**Step 5: Write the model**

`app/Domain/Collaboration/Models/MomCirculation.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Models;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MomCirculation extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'minutes_of_meeting_id',
        'sent_by',
        'round',
        'subject',
        'body_note',
        'deadline_at',
        'closed_at',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
            'closed_at' => 'datetime',
            'round' => 'integer',
        ];
    }

    protected static function newFactory(): \Database\Factories\MomCirculationFactory
    {
        return \Database\Factories\MomCirculationFactory::new();
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MomCirculationRecipient::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /**
     * Recipients we may legitimately treat as silently consenting. A person whose
     * mail never left the building cannot be deemed to have agreed to anything,
     * so delivery failures drop out of the tally entirely rather than counting
     * as silence.
     */
    public function tallyableRecipients(): HasMany
    {
        return $this->recipients()->whereNull('delivery_failed_at');
    }
}
```

**Step 6: Write the factory**

`database/factories/MomCirculationFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Collaboration\Models\MomCirculation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MomCirculation> */
class MomCirculationFactory extends Factory
{
    protected $model = MomCirculation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'round' => 1,
            'subject' => fake()->sentence(),
            'body_note' => null,
            'deadline_at' => now()->addDays(3),
            'status' => 'open',
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed_approved', 'closed_at' => now()]);
    }

    public function awaitingSecretary(): static
    {
        return $this->state(fn () => ['status' => 'awaiting_secretary']);
    }
}
```

**Step 7: Run the tests**

```bash
php artisan test --compact --filter=MomCirculationTest
```

Expected: PASS (2 tests).

**Step 8: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_08_04_100000_create_mom_circulations_table.php \
        app/Domain/Collaboration/Models/MomCirculation.php \
        database/factories/MomCirculationFactory.php \
        tests/Feature/Domain/Collaboration/MomCirculationTest.php
git commit -m "feat(mom): add mom_circulations table and model"
```

---

### Task 3: `mom_circulation_recipients` table and model

**Files:**
- Create: `database/migrations/2026_08_04_100001_create_mom_circulation_recipients_table.php`
- Create: `app/Domain/Collaboration/Models/MomCirculationRecipient.php`
- Create: `database/factories/MomCirculationRecipientFactory.php`
- Test: `tests/Feature/Domain/Collaboration/MomCirculationRecipientTest.php`

**Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mom_circulation_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mom_circulation_id')->constrained()->cascadeOnDelete();

            // Nullable on purpose. Name and email below are snapshotted at send
            // time so the record "Ahmad confirmed on 5 August" stays true even if
            // the attendee is later renamed or deleted. An audit row must not
            // shift when live data shifts.
            $table->foreignId('mom_attendee_id')->nullable()->constrained('mom_attendees')->nullOnDelete();
            $table->string('name');
            $table->string('email');

            $table->string('token', 64)->unique();

            $table->string('response', 20)->nullable();

            // dateTime everywhere below — see the note in the circulations migration.
            // responded_at means "they opened it and pressed a button".
            // deemed_confirmed_at means "they stayed silent until the deadline".
            // Collapsing these two into one flag would destroy the single most
            // important fact this feature produces.
            $table->dateTime('responded_at')->nullable();
            $table->dateTime('deemed_confirmed_at')->nullable();
            $table->dateTime('first_opened_at')->nullable();
            $table->dateTime('last_opened_at')->nullable();
            $table->dateTime('delivery_failed_at')->nullable();

            $table->unsignedInteger('open_count')->default(0);
            $table->string('responded_ip', 45)->nullable();
            $table->text('responded_user_agent')->nullable();

            $table->timestamps();

            $table->index(['mom_circulation_id', 'response']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mom_circulation_recipients');
    }
};
```

Confirm the attendees table name before running — it is `mom_attendees` (see `app/Domain/Attendee/Models/MomAttendee.php`). If the FK fails, check with:

```bash
php artisan db:table mom_attendees
```

**Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Models\MomCirculation;
use App\Domain\Collaboration\Models\MomCirculationRecipient;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->owner = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->owner->id,
    ]);
    $this->circulation = MomCirculation::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'sent_by' => $this->owner->id,
    ]);
});

test('a recipient keeps its own token and starts with no response', function () {
    $recipient = MomCirculationRecipient::factory()->create([
        'organization_id' => $this->org->id,
        'mom_circulation_id' => $this->circulation->id,
    ]);

    expect($recipient->token)->toHaveLength(64)
        ->and($recipient->response)->toBeNull()
        ->and($recipient->open_count)->toBe(0);
});

test('explicit confirmation and deemed confirmation are recorded separately', function () {
    $explicit = MomCirculationRecipient::factory()->confirmed()->create([
        'organization_id' => $this->org->id,
        'mom_circulation_id' => $this->circulation->id,
    ]);

    $silent = MomCirculationRecipient::factory()->create([
        'organization_id' => $this->org->id,
        'mom_circulation_id' => $this->circulation->id,
        'deemed_confirmed_at' => now(),
    ]);

    expect($explicit->responded_at)->not->toBeNull()
        ->and($explicit->deemed_confirmed_at)->toBeNull()
        ->and($silent->responded_at)->toBeNull()
        ->and($silent->deemed_confirmed_at)->not->toBeNull()
        ->and($explicit->isConfirmed())->toBeTrue()
        ->and($silent->isConfirmed())->toBeTrue()
        ->and($explicit->confirmationMethod())->toBe('explicit')
        ->and($silent->confirmationMethod())->toBe('deemed');
});

test('a recipient whose delivery failed drops out of the tally', function () {
    MomCirculationRecipient::factory()->count(2)->create([
        'organization_id' => $this->org->id,
        'mom_circulation_id' => $this->circulation->id,
    ]);
    MomCirculationRecipient::factory()->create([
        'organization_id' => $this->org->id,
        'mom_circulation_id' => $this->circulation->id,
        'delivery_failed_at' => now(),
    ]);

    expect($this->circulation->recipients()->count())->toBe(3)
        ->and($this->circulation->tallyableRecipients()->count())->toBe(2);
});
```

**Step 3: Run it and confirm it fails**

```bash
php artisan test --compact --filter=MomCirculationRecipientTest
```

**Step 4: Write the model**

`app/Domain/Collaboration/Models/MomCirculationRecipient.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Models;

use App\Domain\Attendee\Models\MomAttendee;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MomCirculationRecipient extends Model
{
    use BelongsToOrganization, HasFactory;

    protected $fillable = [
        'mom_circulation_id',
        'mom_attendee_id',
        'name',
        'email',
        'token',
        'response',
        'responded_at',
        'deemed_confirmed_at',
        'first_opened_at',
        'last_opened_at',
        'delivery_failed_at',
        'open_count',
        'responded_ip',
        'responded_user_agent',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'deemed_confirmed_at' => 'datetime',
            'first_opened_at' => 'datetime',
            'last_opened_at' => 'datetime',
            'delivery_failed_at' => 'datetime',
            'open_count' => 'integer',
        ];
    }

    protected static function newFactory(): \Database\Factories\MomCirculationRecipientFactory
    {
        return \Database\Factories\MomCirculationRecipientFactory::new();
    }

    public function circulation(): BelongsTo
    {
        return $this->belongsTo(MomCirculation::class, 'mom_circulation_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(MomAttendee::class, 'mom_attendee_id');
    }

    public function remarks(): HasMany
    {
        return $this->hasMany(Comment::class, 'mom_circulation_recipient_id');
    }

    public function isConfirmed(): bool
    {
        return $this->response === 'confirmed' || $this->deemed_confirmed_at !== null;
    }

    public function hasRequestedAmendment(): bool
    {
        return $this->response === 'amendment_requested';
    }

    /**
     * Which of the two very different things happened. The PDF audit page prints
     * this verbatim rather than flattening both into a single tick, because when
     * a MOM is disputed the difference is the whole argument.
     */
    public function confirmationMethod(): ?string
    {
        return match (true) {
            $this->response === 'confirmed' => 'explicit',
            $this->deemed_confirmed_at !== null => 'deemed',
            default => null,
        };
    }

    public function hasOpened(): bool
    {
        return $this->first_opened_at !== null;
    }

    public function scopeUnresponsive(Builder $query): Builder
    {
        return $query->whereNull('response')->whereNull('delivery_failed_at');
    }

    public function scopeNeverOpened(Builder $query): Builder
    {
        return $query->whereNull('first_opened_at')->whereNull('delivery_failed_at');
    }
}
```

**Step 5: Write the factory**

`database/factories/MomCirculationRecipientFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Collaboration\Models\MomCirculationRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MomCirculationRecipient> */
class MomCirculationRecipientFactory extends Factory
{
    protected $model = MomCirculationRecipient::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'token' => Str::random(64),
            'response' => null,
            'open_count' => 0,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'response' => 'confirmed',
            'responded_at' => now(),
            'first_opened_at' => now()->subMinutes(5),
            'last_opened_at' => now(),
            'open_count' => 1,
        ]);
    }

    public function requestedAmendment(): static
    {
        return $this->state(fn () => [
            'response' => 'amendment_requested',
            'responded_at' => now(),
            'first_opened_at' => now()->subMinutes(5),
            'open_count' => 1,
        ]);
    }

    public function opened(): static
    {
        return $this->state(fn () => [
            'first_opened_at' => now()->subMinutes(5),
            'last_opened_at' => now(),
            'open_count' => 1,
        ]);
    }
}
```

**Step 6: Run and commit**

```bash
php artisan test --compact --filter=MomCirculationRecipientTest
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_08_04_100001_create_mom_circulation_recipients_table.php \
        app/Domain/Collaboration/Models/MomCirculationRecipient.php \
        database/factories/MomCirculationRecipientFactory.php \
        tests/Feature/Domain/Collaboration/MomCirculationRecipientTest.php
git commit -m "feat(mom): add per-attendee circulation recipients with own tokens"
```

---

### Task 4: Let guests comment

`comments.user_id` is `NOT NULL` with an FK. That schema line — not any policy — is the reason a guest cannot leave a remark today.

**Files:**
- Create: `database/migrations/2026_08_04_100002_allow_guest_comments.php`
- Modify: `app/Domain/Collaboration/Models/Comment.php`
- Test: `tests/Feature/Domain/Collaboration/GuestCommentTest.php`

**Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('mom_circulation_recipient_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->cascadeOnDelete();
        });

        Schema::table('comments', function (Blueprint $table) {
            // A guest remark has no user. Note for MySQL: changing a column
            // requires restating every attribute it already had, or the others
            // are dropped — user_id was an unsigned bigint FK to users.
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mom_circulation_recipient_id');
        });
    }
};
```

**Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\MomCirculation;
use App\Domain\Collaboration\Models\MomCirculationRecipient;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->owner = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->owner->id,
    ]);
    $this->circulation = MomCirculation::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'sent_by' => $this->owner->id,
    ]);
    $this->recipient = MomCirculationRecipient::factory()->create([
        'organization_id' => $this->org->id,
        'mom_circulation_id' => $this->circulation->id,
        'name' => 'Hasimah Baharuddin',
    ]);
});

test('a guest recipient can leave a remark without a user account', function () {
    $comment = Comment::createForOrganization($this->org->id, [
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'mom_circulation_recipient_id' => $this->recipient->id,
        'body' => 'Kos yang dibincang RM50k, bukan RM15k.',
        'client_visible' => true,
    ]);

    expect($comment->user_id)->toBeNull()
        ->and($comment->authorName())->toBe('Hasimah Baharuddin');
});

test('a comment cannot claim both a user and a guest recipient', function () {
    expect(fn () => Comment::createForOrganization($this->org->id, [
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'user_id' => $this->owner->id,
        'mom_circulation_recipient_id' => $this->recipient->id,
        'body' => 'Impossible author',
    ]))->toThrow(LogicException::class);
});

test('a comment must have some author', function () {
    expect(fn () => Comment::createForOrganization($this->org->id, [
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'body' => 'Orphan',
    ]))->toThrow(LogicException::class);
});
```

**Step 3: Run it and confirm it fails**

```bash
php artisan test --compact --filter=GuestCommentTest
```

**Step 4: Update the model**

In `app/Domain/Collaboration/Models/Comment.php` — add `'mom_circulation_recipient_id'` to `$fillable`, then add:

```php
    protected static function booted(): void
    {
        static::saving(function (self $comment): void {
            $hasUser = $comment->user_id !== null;
            $hasGuest = $comment->mom_circulation_recipient_id !== null;

            // SQLite in tests cannot express this as a CHECK constraint, so the
            // invariant lives here. A comment with two authors, or none, would
            // make the amendment queue unattributable.
            if ($hasUser === $hasGuest) {
                throw new \LogicException(
                    'A comment must have exactly one author: either user_id or mom_circulation_recipient_id.'
                );
            }
        });
    }

    public function circulationRecipient(): BelongsTo
    {
        return $this->belongsTo(MomCirculationRecipient::class, 'mom_circulation_recipient_id');
    }

    public function authorName(): string
    {
        return $this->user?->name
            ?? $this->circulationRecipient?->name
            ?? __('Unknown');
    }
```

**Step 5: Run the whole comment-related suite**

Existing code creates comments with a `user_id`, so the new invariant could break it. Check:

```bash
php artisan test --compact --filter=Comment
php artisan test --compact --filter=GuestAccess
```

If anything now fails that passed before, the invariant is catching a real pre-existing bug or a factory that omits `user_id` — fix `database/factories/CommentFactory.php` to always supply one rather than weakening the invariant.

**Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/2026_08_04_100002_allow_guest_comments.php \
        app/Domain/Collaboration/Models/Comment.php \
        tests/Feature/Domain/Collaboration/GuestCommentTest.php
git commit -m "feat(mom): allow guest recipients to author comments"
```

**Checkpoint:** run the full suite and compare the failure count against your baseline.

```bash
php artisan test --compact 2>&1 | tail -5
```

---

## Phase 2 — Circulating

### Task 5: `CirculationService::circulate()`

**Files:**
- Create: `app/Domain/Collaboration/Services/CirculationService.php`
- Test: `tests/Feature/Domain/Collaboration/CirculationServiceTest.php`

**Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Services\CirculationService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->owner = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->owner->id,
        'status' => MeetingStatus::Finalized,
    ]);
    $this->service = app(CirculationService::class);
});

test('circulating creates one recipient per attendee that has an email', function () {
    MomAttendee::factory()->count(3)->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'email' => fn () => fake()->unique()->safeEmail(),
    ]);
    MomAttendee::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'email' => null,
    ]);

    $circulation = $this->service->circulate(
        $this->meeting,
        $this->owner,
        deadlineAt: now()->addDays(3),
        subject: 'Please confirm',
    );

    expect($circulation->recipients)->toHaveCount(3)
        ->and($circulation->round)->toBe(1);
});

test('circulating moves the meeting to pending confirmation', function () {
    MomAttendee::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'email' => 'a@example.com',
    ]);

    $this->service->circulate($this->meeting, $this->owner, now()->addDays(3), 'Confirm');

    expect($this->meeting->fresh()->status)->toBe(MeetingStatus::PendingConfirmation);
});

test('only a finalized meeting can be circulated', function () {
    $this->meeting->update(['status' => MeetingStatus::Draft]);

    expect(fn () => $this->service->circulate($this->meeting, $this->owner, now()->addDays(3), 'Confirm'))
        ->toThrow(DomainException::class);
});

test('every recipient gets a distinct token', function () {
    MomAttendee::factory()->count(5)->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'email' => fn () => fake()->unique()->safeEmail(),
    ]);

    $circulation = $this->service->circulate($this->meeting, $this->owner, now()->addDays(3), 'Confirm');

    expect($circulation->recipients->pluck('token')->unique())->toHaveCount(5);
});

test('a second round increments the round number', function () {
    MomAttendee::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'email' => 'a@example.com',
    ]);

    $this->service->circulate($this->meeting, $this->owner, now()->addDays(3), 'Round one');
    $second = $this->service->circulate($this->meeting, $this->owner, now()->addDays(3), 'Round two', force: true);

    expect($second->round)->toBe(2);
});
```

**Step 2: Run and confirm failure**

```bash
php artisan test --compact --filter=CirculationServiceTest
```

**Step 3: Write the service**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\Models\MomCirculation;
use App\Domain\Collaboration\Models\MomCirculationRecipient;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CirculationService
{
    /**
     * Open a confirmation round on a finalized MOM.
     *
     * @param  bool  $force  allow circulating a meeting already pending confirmation,
     *                       which is how round 2 opens after a material amendment
     */
    public function circulate(
        MinutesOfMeeting $meeting,
        User $sentBy,
        CarbonInterface $deadlineAt,
        string $subject,
        ?string $bodyNote = null,
        bool $force = false,
    ): MomCirculation {
        $allowed = $force
            ? [MeetingStatus::Finalized, MeetingStatus::PendingConfirmation]
            : [MeetingStatus::Finalized];

        if (! in_array($meeting->status, $allowed, true)) {
            throw new \DomainException(__('Only finalized minutes can be circulated for confirmation.'));
        }

        return DB::transaction(function () use ($meeting, $sentBy, $deadlineAt, $subject, $bodyNote) {
            $round = (int) MomCirculation::query()
                ->where('minutes_of_meeting_id', $meeting->id)
                ->max('round') + 1;

            $circulation = MomCirculation::createForOrganization($meeting->organization_id, [
                'minutes_of_meeting_id' => $meeting->id,
                'sent_by' => $sentBy->id,
                'round' => $round,
                'subject' => $subject,
                'body_note' => $bodyNote,
                'deadline_at' => $deadlineAt,
                'status' => 'open',
            ]);

            $attendees = $meeting->attendees()->whereNotNull('email')->get();

            foreach ($attendees as $attendee) {
                // Name and email are copied, not referenced. See the migration note.
                MomCirculationRecipient::createForOrganization($meeting->organization_id, [
                    'mom_circulation_id' => $circulation->id,
                    'mom_attendee_id' => $attendee->id,
                    'name' => $attendee->name,
                    'email' => $attendee->email,
                    'token' => Str::random(64),
                ]);
            }

            $meeting->update(['status' => MeetingStatus::PendingConfirmation]);

            return $circulation->load('recipients');
        });
    }

    /** Attendees who cannot be reached, so the secretary is never misled about coverage. */
    public function unreachableAttendees(MinutesOfMeeting $meeting): \Illuminate\Support\Collection
    {
        return $meeting->attendees()->whereNull('email')->get();
    }
}
```

**Step 4: Run, then commit**

```bash
php artisan test --compact --filter=CirculationServiceTest
vendor/bin/pint --dirty --format agent
git add app/Domain/Collaboration/Services/CirculationService.php \
        tests/Feature/Domain/Collaboration/CirculationServiceTest.php
git commit -m "feat(mom): add CirculationService to open a confirmation round"
```

---

### Task 6: Lock edits while pending confirmation

People are midway through confirming. If the text shifts under them, their confirmation means nothing.

**Files:**
- Modify: `app/Domain/Meeting/Services/MeetingService.php:86`
- Modify: `app/Domain/Meeting/Policies/MinutesOfMeetingPolicy.php:56`
- Test: `tests/Feature/Domain/Meeting/PendingConfirmationLockTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Services\MeetingService;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('minutes pending confirmation cannot be edited directly', function () {
    $org = Organization::factory()->create();
    $owner = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $owner->id,
        'status' => MeetingStatus::PendingConfirmation,
    ]);

    expect(fn () => app(MeetingService::class)->update($meeting, ['title' => 'Changed'], $owner))
        ->toThrow(DomainException::class);
});
```

Check the real signature of `MeetingService::update()` at `app/Domain/Meeting/Services/MeetingService.php:86` and match it — the argument order above is a guess.

**Step 2: Run, confirm failure, then implement**

In `MeetingService::update()`, extend the existing guard:

```php
        if ($mom->status === MeetingStatus::Approved) {
            throw new \DomainException(__('Cannot edit an approved meeting.'));
        }

        if ($mom->status === MeetingStatus::PendingConfirmation) {
            throw new \DomainException(__('Cannot edit minutes while they are out for confirmation. Cancel the circulation first.'));
        }
```

In `MinutesOfMeetingPolicy::update()`, add `PendingConfirmation` alongside the existing `Approved` check so the UI hides the edit affordance rather than letting people hit the exception.

**Step 3: Run and commit**

```bash
php artisan test --compact --filter=PendingConfirmationLockTest
vendor/bin/pint --dirty --format agent
git commit -am "feat(mom): lock edits while minutes are out for confirmation"
```

---

### Task 7: Circulate route, request and controller

**Files:**
- Create: `app/Domain/Collaboration/Requests/CirculateRequest.php`
- Create: `app/Domain/Collaboration/Controllers/CirculationController.php`
- Modify: `routes/web.php` (authenticated group, near line 292 beside the existing guest-access routes)
- Test: `tests/Feature/Domain/Collaboration/Controllers/CirculationControllerTest.php`

**Step 1: Write the request**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CirculateRequest extends FormRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'body_note' => ['nullable', 'string', 'max:2000'],
            'deadline_at' => ['required', 'date', 'after:now'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'deadline_at.after' => __('The confirmation deadline must be in the future.'),
        ];
    }
}
```

Check whether sibling requests in `app/Domain/Collaboration/Requests/` use array or string rule syntax and match them.

**Step 2: Write the controller**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Controllers;

use App\Domain\Collaboration\Requests\CirculateRequest;
use App\Domain\Collaboration\Services\CirculationService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class CirculationController extends Controller
{
    public function __construct(private readonly CirculationService $circulations) {}

    public function store(CirculateRequest $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        abort_unless($meeting->organization_id === $request->user()->current_organization_id, 403);
        $this->authorize('approve', $meeting);

        $circulation = $this->circulations->circulate(
            $meeting,
            $request->user(),
            \Illuminate\Support\Carbon::parse($request->validated('deadline_at')),
            $request->validated('subject'),
            $request->validated('body_note'),
        );

        return redirect()
            ->route('meetings.show', $meeting)
            ->with('success', __(':count recipients have been asked to confirm these minutes.', [
                'count' => $circulation->recipients->count(),
            ]));
    }

    public function destroy(MinutesOfMeeting $meeting, \App\Domain\Collaboration\Models\MomCirculation $circulation): RedirectResponse
    {
        abort_unless($circulation->organization_id === request()->user()->current_organization_id, 403);
        $this->authorize('approve', $meeting);

        $this->circulations->cancel($circulation);

        return redirect()->route('meetings.show', $meeting)
            ->with('success', __('Circulation cancelled. These minutes can be edited again.'));
    }
}
```

Add `cancel()` to `CirculationService`: set `status = 'cancelled'`, `closed_at = now()`, and return the meeting to `MeetingStatus::Finalized`.

**Step 3: Register the routes**

In `routes/web.php`, inside the authenticated group beside the guest-access routes near line 292:

```php
    // MOM confirmation circulation
    Route::post('meetings/{meeting}/circulate', [\App\Domain\Collaboration\Controllers\CirculationController::class, 'store'])
        ->name('meetings.circulate.store');
    Route::delete('meetings/{meeting}/circulate/{circulation}', [\App\Domain\Collaboration\Controllers\CirculationController::class, 'destroy'])
        ->name('meetings.circulate.destroy');
```

**Step 4: Write controller tests** covering: a member of another org gets 403; a draft meeting gets a validation or domain error; a successful post creates the circulation and redirects with the recipient count; a past deadline fails validation.

**Step 5: Run and commit**

```bash
php artisan test --compact --filter=CirculationControllerTest
vendor/bin/pint --dirty --format agent
git add app/Domain/Collaboration/Requests/CirculateRequest.php \
        app/Domain/Collaboration/Controllers/CirculationController.php \
        app/Domain/Collaboration/Services/CirculationService.php \
        routes/web.php \
        tests/Feature/Domain/Collaboration/Controllers/CirculationControllerTest.php
git commit -m "feat(mom): add circulate and cancel endpoints"
```

---

## Phase 3 — The guest confirmation page

### Task 8: Guest route and controller

**Files:**
- Create: `app/Domain/Collaboration/Controllers/ConfirmationController.php`
- Create: `resources/views/mom/confirm.blade.php`
- Modify: `routes/web.php` (public throttled group, near line 71)
- Test: `tests/Feature/Domain/Collaboration/Controllers/ConfirmationControllerTest.php`

**Step 1: Register the routes**

Inside the existing `Route::middleware('throttle:10,1')->group(...)` block that already holds `guest/{token}`:

```php
    Route::get('mom/confirm/{token}', [\App\Domain\Collaboration\Controllers\ConfirmationController::class, 'show'])
        ->name('mom.confirm.show');
```

And a separate, tighter group for the write actions:

```php
Route::middleware('throttle:20,1')->group(function () {
    Route::post('mom/confirm/{token}/confirm', [\App\Domain\Collaboration\Controllers\ConfirmationController::class, 'confirm'])
        ->name('mom.confirm.confirm');
    Route::post('mom/confirm/{token}/withdraw', [\App\Domain\Collaboration\Controllers\ConfirmationController::class, 'withdraw'])
        ->name('mom.confirm.withdraw');
    Route::post('mom/confirm/{token}/remark', [\App\Domain\Collaboration\Controllers\ConfirmationController::class, 'remark'])
        ->name('mom.confirm.remark');
    Route::post('mom/confirm/{token}/submit-amendments', [\App\Domain\Collaboration\Controllers\ConfirmationController::class, 'submitAmendments'])
        ->name('mom.confirm.submit-amendments');
});
```

**Step 2: Write the failing tests**

```php
test('a valid token shows the minutes and the recipient name', function () { /* ... */ });
test('an unknown token returns 404', function () { /* ... */ });
test('opening records first_opened_at and increments open_count', function () { /* ... */ });
test('opening twice does not overwrite first_opened_at', function () { /* ... */ });
test('a cancelled circulation shows the closed state rather than the action bar', function () { /* ... */ });
```

Write these out fully before implementing. The never-overwrite-`first_opened_at` case matters: it is the evidence that the person was reached at all, and the deadline job's hold condition depends on it.

**Step 3: Write the controller**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Controllers;

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\MomCirculationRecipient;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConfirmationController
{
    public function show(string $token, Request $request): View
    {
        $recipient = $this->resolve($token);

        // Never overwrite the first open: it is the proof this person was
        // actually reached, which is what the deadline job checks before it is
        // willing to treat anyone's silence as agreement.
        $recipient->forceFill([
            'first_opened_at' => $recipient->first_opened_at ?? now(),
            'last_opened_at' => now(),
            'open_count' => $recipient->open_count + 1,
        ])->save();

        $circulation = $recipient->circulation;

        $meeting = MinutesOfMeeting::withoutGlobalScopes()
            ->whereKey($circulation->minutes_of_meeting_id)
            ->with([
                'organization',
                'topics' => fn ($q) => $q->withoutGlobalScopes()->orderBy('sort_order'),
                'attendees' => fn ($q) => $q->withoutGlobalScopes(),
                'actionItems' => fn ($q) => $q->withoutGlobalScopes()->with('assignedTo:id,name'),
            ])
            ->firstOrFail();

        $myRemarks = Comment::withoutGlobalScopes()
            ->where('mom_circulation_recipient_id', $recipient->id)
            ->get()
            ->groupBy(fn (Comment $c) => $c->commentable_type.':'.$c->commentable_id);

        return view('mom.confirm', compact('recipient', 'circulation', 'meeting', 'myRemarks'));
    }

    /**
     * Global scopes are dropped deliberately: this route has no authenticated
     * user, so OrganizationScope would resolve to 1=0 and every lookup here
     * would come back empty.
     */
    private function resolve(string $token): MomCirculationRecipient
    {
        return MomCirculationRecipient::withoutGlobalScopes()
            ->where('token', $token)
            ->with(['circulation' => fn ($q) => $q->withoutGlobalScopes()])
            ->firstOrFail();
    }
}
```

**Step 4: Run, then commit.**

---

### Task 9: The confirmation view

**Files:**
- Create: `resources/views/mom/confirm.blade.php`
- Create: `resources/views/mom/partials/_confirm-actionbar.blade.php`
- Create: `resources/views/mom/partials/_deadline-banner.blade.php`

Start from `resources/views/meetings/guest.blade.php` — it already renders the header, topics, attendees, action items and comments correctly, including dark mode. Copy it and layer on:

1. **Deadline banner, sticky at the top.** It must state plainly that no response means the minutes are confirmed. We auto-approve on silence, so this cannot be fine print — if someone later disputes it, our whole defence is that the warning was impossible to miss.
2. **Identity strip** — `You: {{ $recipient->name }} ({{ $recipient->email }})` plus a *Not you?* link that reports a misdirected link.
3. **Per-row remark buttons** on each topic and action item.
4. **Unanchored "Something missing?" box** at the bottom of the document.
5. **Sticky bottom action bar** whose primary button flips based on whether unsent remarks exist.

**Escaping — read this before writing a single Alpine attribute.** Interpolating Blade into JavaScript with `{{ }}` is a recurring critical vulnerability in this codebase; it was found four times in one audit, including stored XSS reachable by a superadmin. `{{ }}` HTML-decodes inside an attribute and breaks straight out of the string. Use bare `@js()` for every value crossing into `x-data`, `x-on:` or an inline handler:

```blade
<div x-data="{ recipient: @js($recipient->name), remarks: @js($myRemarks->keys()) }">
```

Never `x-data="{ name: '{{ $recipient->name }}' }"`. An attendee named `O'Brien` alone has already broken a wizard step in this project.

**Mobile first.** These links arrive by email and WhatsApp and are opened on phones. Action bar fixed to the bottom of the viewport, remark composer as a bottom sheet, action items as stacked cards below `sm`.

**Commit** once it renders in a browser test or a manual check at the URL from `get-absolute-url`.

---

### Task 10: Confirm and withdraw

**Files:**
- Modify: `app/Domain/Collaboration/Controllers/ConfirmationController.php`
- Create: `app/Domain/Collaboration/Services/ConfirmationService.php`
- Test: extend `ConfirmationControllerTest.php`

**Tests to write first:**

```php
test('confirming records the response, timestamp, ip and user agent', function () { /* ... */ });
test('confirming does not set deemed_confirmed_at', function () { /* ... */ });
test('a recipient can withdraw a confirmation before the deadline', function () { /* ... */ });
test('a recipient cannot confirm after the circulation has closed', function () { /* ... */ });
test('confirming with unsent remarks requires the acknowledge flag', function () { /* ... */ });
```

The second test is the important one. `responded_at` and `deemed_confirmed_at` must never both be set on the same row — that pair is the entire evidentiary value of the table, and the PDF audit page reads them to decide what to print.

Withdrawal stays open until the deadline. Confirmation that cannot be undone makes people hesitate to press the button, and hesitation kills the response rate the feature depends on.

---

## Phase 4 — Anchored remarks

### Task 11: Store a remark against a topic or action item

**Files:**
- Modify: `app/Domain/Collaboration/Controllers/ConfirmationController.php`
- Create: `app/Domain/Collaboration/Requests/GuestRemarkRequest.php`

The anchor is polymorphic and must be validated against a whitelist — never trust a `commentable_type` from the request body:

```php
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'anchor_type' => ['nullable', 'string', 'in:topic,action_item'],
            'anchor_id' => ['nullable', 'integer', 'required_with:anchor_type'],
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
```

Map `topic` to `App\Domain\AI\Models\MomTopic` and `action_item` to `App\Domain\ActionItem\Models\ActionItem` in the controller. `MomTopic` lives under the **AI** domain, not Meeting — check the import.

Then verify the anchor actually belongs to this meeting before saving. Without that check, a guest could attach remarks to another organisation's topics by guessing an id.

**Tests:**

```php
test('a remark can be anchored to a topic in this meeting', function () { /* ... */ });
test('a remark anchored to a topic from another meeting is rejected', function () { /* ... */ });
test('an unknown anchor type is rejected', function () { /* ... */ });
test('an unanchored remark attaches to the meeting itself', function () { /* ... */ });
```

### Task 12: Submitting amendments

Submitting flips `response` to `amendment_requested`, sets `responded_at`, and marks the recipient's remarks `client_visible`. Notify the secretary.

**Test:** a recipient with three remarks who submits ends with `response === 'amendment_requested'` and three visible remarks, and the meeting stays `PendingConfirmation`.

---

## Phase 5 — Secretary tooling

### Task 13: Monitoring panel

**Files:**
- Create: `app/Domain/Collaboration/Services/CirculationTallyService.php`
- Create: `resources/views/meetings/partials/_circulation-panel.blade.php`
- Modify: `resources/views/meetings/show.blade.php`

The tally returns confirmed / amendments / opened-but-silent / never-opened / delivery-failed.

Sort **never-opened to the top**. Someone who read the minutes and stayed quiet is a soft yes; someone who never opened them is the real liability once silence starts counting as consent. The panel should make that group impossible to overlook, with the reminder button next to it.

### Task 14: Reminders

**Files:**
- Create: `app/Domain/Collaboration/Jobs/SendCirculationRemindersJob.php`
- Create: `app/Domain/Collaboration/Mail/CirculationReminderMail.php`

Different copy for never-opened (*"You haven't opened these minutes"*) versus read-but-silent (*"24 hours left to respond"*). Also reachable from the panel button.

**Test:** `Mail::fake()`, assert only unresponsive recipients are mailed, and that a recipient with `delivery_failed_at` is skipped.

### Task 15: Amendment queue, and the minor/material decision

**Files:**
- Create: `app/Domain/Collaboration/Controllers/AmendmentController.php`
- Create: `resources/views/meetings/partials/_amendment-queue.blade.php`

Accepting an amendment requires an explicit `severity` of `minor` or `material` — there is no default:

| `severity` | Effect |
|---|---|
| `minor` | Prior confirmations stand. `VersionService` records a version. Recipients are notified, no re-confirmation. |
| `material` | All prior confirmations are voided, current circulation closes as `closed_amended`, and round 2 opens with a fresh deadline via `circulate(..., force: true)`. |

Log the choice with the secretary's name through `AuditService`. If the MOM is later challenged, the record must show a person made that judgement deliberately rather than the system quietly deciding on their behalf. Do not infer severity from the diff.

**Tests:**

```php
test('accepting a minor amendment keeps prior confirmations', function () { /* ... */ });
test('accepting a material amendment voids confirmations and opens round 2', function () { /* ... */ });
test('accepting an amendment without a severity is rejected', function () { /* ... */ });
test('accepting an amendment creates a new version', function () { /* ... */ });
test('the severity choice is written to the audit log with the actor name', function () { /* ... */ });
```

---

## Phase 6 — Deadline and auto-approve

### Task 16: Let `MeetingApproved` carry a non-human actor

`MeetingService::approve()` requires a `User`, and this job runs with nobody logged in.

**Do not pass the `sent_by` user as a stand-in.** The secretary did not approve these minutes — the deadline did. Faking the actor in an audit trail to dodge a small refactor would corrupt the one thing this feature sells.

**Files:**
- Modify: `app/Domain/Meeting/Events/MeetingApproved.php`
- Modify: `app/Domain/Meeting/Listeners/NotifyMeetingApproved.php`
- Modify: `app/Domain/Meeting/Notifications/MeetingApprovedNotification.php`
- Modify: `app/Domain/Meeting/Services/MeetingService.php:156`
- Test: `tests/Feature/Domain/Meeting/AutomaticApprovalTest.php`

Make `approvedBy` nullable and add the circulation:

```php
    public function __construct(
        public readonly MinutesOfMeeting $meeting,
        public readonly ?User $approvedBy = null,
        public readonly ?MomCirculation $circulation = null,
    ) {}
```

`MeetingApprovedNotification` reads `$this->approvedBy->name` in three places (`toMail`, `toTeams`, `toArray`). Give it one accessor:

```php
    private function actorName(): string
    {
        return $this->approvedBy?->name
            ?? __('Automatic confirmation · Round :round', ['round' => $this->circulation?->round ?? 1]);
    }
```

`WebhookEventSubscriber::handleMeetingApproved` does not read `approvedBy` at all, so it needs no change — but re-read it to confirm before assuming.

Add `MeetingService::approveByCirculation(MinutesOfMeeting $mom, MomCirculation $circulation)` which mirrors `approve()` but logs the actor as the circulation.

**Test:** an automatic approval dispatches `MeetingApproved` with `approvedBy === null` and a non-null circulation, and the notification renders without a fatal error.

### Task 17: `CloseExpiredCirculationsJob`

**Files:**
- Create: `app/Domain/Collaboration/Jobs/CloseExpiredCirculationsJob.php`
- Test: `tests/Feature/Domain/Collaboration/CloseExpiredCirculationsJobTest.php`

**Write these tests first — they encode the safety rules:**

```php
test('a circulation past its deadline with no objections approves the meeting', function () { /* ... */ });
test('silent recipients get deemed_confirmed_at but not responded_at', function () { /* ... */ });
test('an open amendment request holds the approval', function () { /* ... */ });
test('a held circulation moves to awaiting_secretary and notifies the sender', function () { /* ... */ });
test('a circulation nobody opened is held rather than approved', function () { /* ... */ });
test('recipients whose delivery failed are neither deemed nor blocking', function () { /* ... */ });
test('a circulation before its deadline is untouched', function () { /* ... */ });
test('running the job twice does not approve twice', function () { /* ... */ });
```

The job body:

```php
    public function handle(MeetingService $meetings): void
    {
        MomCirculation::withoutGlobalScopes()
            ->open()
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', now())
            ->with(['recipients', 'meeting'])
            ->each(fn (MomCirculation $c) => $this->close($c, $meetings));
    }

    private function close(MomCirculation $circulation, MeetingService $meetings): void
    {
        DB::transaction(function () use ($circulation, $meetings) {
            $fresh = MomCirculation::withoutGlobalScopes()
                ->lockForUpdate()
                ->find($circulation->id);

            // Two workers colliding must not produce two MeetingApproved events
            // and two rounds of mail to ten people.
            if ($fresh === null || $fresh->status !== 'open') {
                return;
            }

            $tallyable = $fresh->tallyableRecipients()->get();

            $hasObjection = $tallyable->contains(fn ($r) => $r->hasRequestedAmendment());

            // If nobody opened it, the silence means the mail hit spam. Approving
            // a document nobody read is not defensible, so it goes to a human.
            $nobodyOpened = $tallyable->every(fn ($r) => ! $r->hasOpened());

            if ($hasObjection || $nobodyOpened || $tallyable->isEmpty()) {
                $fresh->update(['status' => 'awaiting_secretary']);
                // notify $fresh->sentBy
                return;
            }

            foreach ($tallyable->whereNull('response') as $silent) {
                // now(), not deadline_at: if the scheduler stalls for six hours,
                // when we decided and when the deadline fell are different facts
                // and both are worth keeping.
                $silent->update(['deemed_confirmed_at' => now()]);
            }

            $fresh->update(['status' => 'closed_approved', 'closed_at' => now()]);
            $meetings->approveByCirculation($fresh->meeting, $fresh);
        });
    }
```

### Task 18: Register the schedule

**Files:**
- Modify: `routes/console.php`

```php
Schedule::job(new CloseExpiredCirculationsJob)->hourly();
Schedule::job(new SendCirculationRemindersJob)->hourly();
```

**Verify it registered:**

```bash
php artisan schedule:list | grep -i circulation
```

**Timezone check — do this manually against MySQL, not SQLite.** A deadline of *"Friday 5:00 PM"* must mean 5pm Malaysian time. This project has already lost 8 hours to a MySQL timestamp issue that SQLite could not see, and a deadline off by 8 hours would approve minutes before people have had a chance to read them. Create a circulation on staging with a known local deadline and confirm `deadline_at` in the database matches what the UI showed.

---

## Phase 7 — Audit artifact

### Task 19: PDF confirmation page

**Files:**
- Modify: `app/Domain/Export/Services/PdfExportService.php`
- Create: `resources/views/exports/partials/_confirmation-page.blade.php`

Append the page only when the meeting reached `Approved` through a circulation. Print, per recipient, the **method** — *explicitly confirmed* versus *no response* — and the timestamp. Keep the two distinct rather than collapsing them into a single tick; that difference is the whole argument when a MOM is disputed.

Include circulation date, round, deadline, recipient count, amendment history, and a verification QR.

**Test:** generate a PDF for an approved-by-circulation meeting and assert the HTML contains both method labels and the correct recipient count.

### Task 20: Public verification page

**Files:**
- Create: `app/Domain/Collaboration/Controllers/VerificationController.php`
- Create: `resources/views/mom/verify.blade.php`
- Modify: `routes/web.php`

Show **integrity facts only** — MOM number, title, date, approval status, confirmation counts, version hash. Never the meeting content. Anyone holding the PDF can then check it is genuine, without the page turning every forwarded PDF into a doorway to the meeting's contents.

**Tests:**

```php
test('the verification page shows the approval status and counts', function () { /* ... */ });
test('the verification page does not leak meeting content', function () { /* ... */ });
test('an unknown hash returns 404', function () { /* ... */ });
```

The second test is a real security assertion — assert the response does **not** see topic titles, the summary or action item text.

---

## Phase 8 — Polish

### Task 21: Print stylesheet

`@media print` on the confirmation and guest views: drop the banner, action bar and remark buttons; keep the document. Every competitor breaks when printed, and secretaries print.

### Task 22: Translations

Add every new string to `lang/ms/` and `lang/en/`. Use namespaced keys — a bare `__('Settings')` returns the entire `settings.php` array on a case-insensitive filesystem and 500s the view on macOS. Follow the `__('nav.settings')` pattern.

**Check:**

```bash
php artisan test --compact --filter=Translation
```

### Task 23: Conversion CTA

Place it **after** the confirm action, never before — the guest page is the customer's document shown to *their* client, and a banner across the top borrows their professional credibility for our marketing. Pre-fill name and email from the recipient snapshot.

**No account is created and no marketing list is joined without the guest's own action.** That email was given to the meeting organiser, not to us.

---

## Final verification

```bash
php artisan test --compact 2>&1 | tail -5
```

Compare against the baseline recorded at the start. The failure count must not have increased.

```bash
vendor/bin/pint --dirty --format agent
npm run build
```

Then walk the whole loop manually on Herd: finalize a meeting → circulate to two addresses → open one token, leave an anchored remark, submit → confirm from the other → accept the amendment as minor → let the deadline pass (or invoke the job by hand) → check the PDF audit page and the verification URL.

```bash
php artisan queue:work --once
php artisan schedule:test
```
