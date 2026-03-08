# Sprint 3: Live Meeting AI Dashboard + AI Meeting Prep Brief — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build two "wow factor" features — a real-time AI dashboard for live meetings and an automated personalized prep brief system that emails attendees 24h before meetings.

**Architecture:** Feature 1 (Prep Brief) uses scheduled jobs to auto-generate personalized AI briefs per attendee, stored in DB and delivered via email + in-app. Feature 2 (Live Dashboard) extends the existing chunked audio recording to stream transcription chunks during meetings, with periodic AI extraction broadcast via Laravel Reverb to all participants.

**Tech Stack:** Laravel 12, PHP 8.4, Alpine.js, Laravel Echo/Reverb, Whisper API, multi-provider AI (OpenAI/Anthropic/Google/Ollama), Pest 4

---

## Part A: AI Meeting Prep Brief

### Task 1: Database Migration — `meeting_prep_briefs` Table

**Files:**
- Create: `database/migrations/2026_03_08_100000_create_meeting_prep_briefs_table.php`

**Step 1: Create migration**

Run: `php artisan make:migration create_meeting_prep_briefs_table --no-interaction`

Then replace contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_prep_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendee_id')->constrained('mom_attendees')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('content');
            $table->json('summary_highlights')->nullable();
            $table->unsignedInteger('estimated_prep_minutes')->default(0);
            $table->timestamp('generated_at');
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->json('sections_read')->nullable();
            $table->timestamps();

            $table->index(['minutes_of_meeting_id', 'attendee_id']);
            $table->index(['user_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_prep_briefs');
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate --no-interaction`
Expected: Migration runs successfully, table created.

**Step 3: Commit**

```bash
git add database/migrations/*_create_meeting_prep_briefs_table.php
git commit -m "feat: add meeting_prep_briefs migration"
```

---

### Task 2: MeetingPrepBrief Model

**Files:**
- Create: `app/Domain/AI/Models/MeetingPrepBrief.php`

**Step 1: Write the failing test**

Create test: `php artisan make:test --pest Domain/AI/Models/MeetingPrepBriefTest --no-interaction`

```php
<?php

use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('belongs to a meeting', function () {
    $brief = MeetingPrepBrief::factory()->create();

    expect($brief->meeting)->toBeInstanceOf(MinutesOfMeeting::class);
});

it('belongs to an attendee', function () {
    $brief = MeetingPrepBrief::factory()->create();

    expect($brief->attendee)->toBeInstanceOf(MomAttendee::class);
});

it('optionally belongs to a user', function () {
    $user = User::factory()->create();
    $brief = MeetingPrepBrief::factory()->create(['user_id' => $user->id]);

    expect($brief->user)->toBeInstanceOf(User::class);
});

it('casts content and summary_highlights as arrays', function () {
    $brief = MeetingPrepBrief::factory()->create([
        'content' => ['executive_summary' => 'Test summary'],
        'summary_highlights' => ['highlight 1', 'highlight 2'],
    ]);

    $fresh = $brief->fresh();

    expect($fresh->content)->toBeArray()
        ->and($fresh->content['executive_summary'])->toBe('Test summary')
        ->and($fresh->summary_highlights)->toBeArray()
        ->and($fresh->summary_highlights)->toHaveCount(2);
});

it('casts date fields as datetime', function () {
    $brief = MeetingPrepBrief::factory()->create([
        'generated_at' => now(),
        'viewed_at' => now(),
        'email_sent_at' => now(),
    ]);

    expect($brief->generated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($brief->viewed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($brief->email_sent_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('marks as viewed', function () {
    $brief = MeetingPrepBrief::factory()->create(['viewed_at' => null]);

    $brief->markAsViewed();

    expect($brief->fresh()->viewed_at)->not->toBeNull();
});

it('tracks sections read', function () {
    $brief = MeetingPrepBrief::factory()->create(['sections_read' => null]);

    $brief->markSectionRead('executive_summary');
    $brief->markSectionRead('action_items');

    $fresh = $brief->fresh();

    expect($fresh->sections_read)->toContain('executive_summary')
        ->and($fresh->sections_read)->toContain('action_items');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=MeetingPrepBriefTest`
Expected: FAIL — class not found

**Step 3: Create the model**

```php
<?php

namespace App\Domain\AI\Models;

use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingPrepBrief extends Model
{
    use HasFactory;

    protected $fillable = [
        'minutes_of_meeting_id',
        'attendee_id',
        'user_id',
        'content',
        'summary_highlights',
        'estimated_prep_minutes',
        'generated_at',
        'email_sent_at',
        'viewed_at',
        'sections_read',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'summary_highlights' => 'array',
            'sections_read' => 'array',
            'generated_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'viewed_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(MomAttendee::class, 'attendee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsViewed(): void
    {
        $this->update(['viewed_at' => now()]);
    }

    public function markSectionRead(string $section): void
    {
        $sections = $this->sections_read ?? [];

        if (! in_array($section, $sections, true)) {
            $sections[] = $section;
            $this->update(['sections_read' => $sections]);
        }
    }
}
```

**Step 4: Create the factory**

Create: `database/factories/Domain/AI/Models/MeetingPrepBriefFactory.php`

Check where other AI model factories live first — if factories are flat in `database/factories/`, follow that pattern. The factory needs to create a MinutesOfMeeting and MomAttendee:

```php
<?php

namespace Database\Factories\Domain\AI\Models;

use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeetingPrepBriefFactory extends Factory
{
    protected $model = MeetingPrepBrief::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $meeting = MinutesOfMeeting::factory()->create();
        $attendee = MomAttendee::factory()->create([
            'minutes_of_meeting_id' => $meeting->id,
        ]);

        return [
            'minutes_of_meeting_id' => $meeting->id,
            'attendee_id' => $attendee->id,
            'user_id' => $attendee->user_id,
            'content' => [
                'executive_summary' => $this->faker->paragraph(),
                'action_items' => [],
                'unresolved_items' => [],
                'agenda_deep_dive' => [],
                'metrics' => [],
                'reading_list' => [],
                'conflicts' => [],
            ],
            'summary_highlights' => [
                $this->faker->sentence(),
                $this->faker->sentence(),
                $this->faker->sentence(),
            ],
            'estimated_prep_minutes' => $this->faker->numberBetween(10, 60),
            'generated_at' => now(),
            'email_sent_at' => null,
            'viewed_at' => null,
            'sections_read' => null,
        ];
    }
}
```

> **Note:** Check existing factory locations. If factories use a different namespace pattern (e.g., `Database\Factories\MeetingPrepBriefFactory`), adjust accordingly. Look at `database/factories/` for existing patterns.

**Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=MeetingPrepBriefTest`
Expected: All tests PASS

**Step 6: Add relationship to MinutesOfMeeting model**

Modify: `app/Domain/Meeting/Models/MinutesOfMeeting.php` — add after the existing `aiConversations()` relationship:

```php
public function prepBriefs(): HasMany
{
    return $this->hasMany(\App\Domain\AI\Models\MeetingPrepBrief::class, 'minutes_of_meeting_id');
}
```

Add `use Illuminate\Database\Eloquent\Relations\HasMany;` if not already imported.

**Step 7: Commit**

```bash
git add app/Domain/AI/Models/MeetingPrepBrief.php database/factories/ tests/Feature/Domain/AI/Models/MeetingPrepBriefTest.php app/Domain/Meeting/Models/MinutesOfMeeting.php
git commit -m "feat: add MeetingPrepBrief model with factory and tests"
```

---

### Task 3: MeetingPrepBriefService

**Files:**
- Create: `app/Domain/AI/Services/MeetingPrepBriefService.php`
- Test: `tests/Feature/Domain/AI/Services/MeetingPrepBriefServiceTest.php`

**Step 1: Write the failing test**

Create test: `php artisan make:test --pest Domain/AI/Services/MeetingPrepBriefServiceTest --no-interaction`

```php
<?php

use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\AI\Services\MeetingPrepBriefService;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\Organization;
use App\Models\User;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\AttendeeRole;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => json_encode([
                    'executive_summary' => 'AI generated summary for this meeting.',
                    'suggested_questions' => ['What is the budget?', 'What are the risks?'],
                    'reading_priorities' => ['Financial Report', 'Risk Assessment'],
                ])]],
            ],
            'usage' => ['total_tokens' => 500],
        ]),
    ]);
});

it('generates a brief for a single attendee', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'status' => MeetingStatus::Draft,
        'meeting_date' => now()->addDay(),
    ]);
    $attendee = MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'user_id' => $user->id,
        'role' => AttendeeRole::Participant,
    ]);

    $service = app(MeetingPrepBriefService::class);
    $brief = $service->generateForAttendee($meeting, $attendee);

    expect($brief)->toBeInstanceOf(MeetingPrepBrief::class)
        ->and($brief->content)->toBeArray()
        ->and($brief->content)->toHaveKeys([
            'executive_summary',
            'action_items',
            'unresolved_items',
            'metrics',
        ])
        ->and($brief->attendee_id)->toBe($attendee->id)
        ->and($brief->minutes_of_meeting_id)->toBe($meeting->id)
        ->and($brief->generated_at)->not->toBeNull();
});

it('includes overdue action items for the attendee', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'status' => MeetingStatus::Draft,
        'meeting_date' => now()->addDay(),
    ]);
    $attendee = MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'user_id' => $user->id,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $org->id,
        'assigned_to' => $user->id,
        'status' => ActionItemStatus::Open,
        'due_date' => now()->subDays(3),
        'title' => 'Overdue task',
    ]);

    $service = app(MeetingPrepBriefService::class);
    $brief = $service->generateForAttendee($meeting, $attendee);

    expect($brief->content['action_items']['overdue'])->toHaveCount(1)
        ->and($brief->content['action_items']['overdue'][0]['title'])->toBe('Overdue task');
});

it('generates briefs for all attendees of a meeting', function () {
    $org = Organization::factory()->create();
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'status' => MeetingStatus::Draft,
        'meeting_date' => now()->addDay(),
    ]);

    MomAttendee::factory()->count(3)->create([
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    $service = app(MeetingPrepBriefService::class);
    $briefs = $service->generateForMeeting($meeting);

    expect($briefs)->toHaveCount(3);
});

it('replaces existing brief when regenerated', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'status' => MeetingStatus::Draft,
        'meeting_date' => now()->addDay(),
    ]);
    $attendee = MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'user_id' => $user->id,
    ]);

    $service = app(MeetingPrepBriefService::class);
    $service->generateForAttendee($meeting, $attendee);
    $service->generateForAttendee($meeting, $attendee);

    expect(MeetingPrepBrief::where('attendee_id', $attendee->id)->count())->toBe(1);
});

it('calculates estimated prep time from documents', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'status' => MeetingStatus::Draft,
        'meeting_date' => now()->addDay(),
    ]);
    $attendee = MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'user_id' => $user->id,
    ]);

    $service = app(MeetingPrepBriefService::class);
    $brief = $service->generateForAttendee($meeting, $attendee);

    expect($brief->estimated_prep_minutes)->toBeGreaterThanOrEqual(0);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=MeetingPrepBriefServiceTest`
Expected: FAIL — class not found

**Step 3: Write the service**

Reference: `app/Domain/AI/Services/MeetingPreparationService.php` for pattern and AI provider setup.

```php
<?php

namespace App\Domain\AI\Services;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\AIProviderFactory;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\MeetingStatus;
use Illuminate\Support\Collection;

class MeetingPrepBriefService
{
    public function __construct(
        private AIProviderFactory $aiProviderFactory,
    ) {}

    /**
     * Generate briefs for all attendees of a meeting.
     *
     * @return Collection<int, MeetingPrepBrief>
     */
    public function generateForMeeting(MinutesOfMeeting $meeting): Collection
    {
        $attendees = $meeting->attendees()->with('user')->get();

        return $attendees->map(fn (MomAttendee $attendee) => $this->generateForAttendee($meeting, $attendee));
    }

    /**
     * Generate a personalized prep brief for a single attendee.
     */
    public function generateForAttendee(MinutesOfMeeting $meeting, MomAttendee $attendee): MeetingPrepBrief
    {
        // Delete existing brief for this attendee/meeting combo
        MeetingPrepBrief::where('minutes_of_meeting_id', $meeting->id)
            ->where('attendee_id', $attendee->id)
            ->delete();

        $content = $this->buildBriefContent($meeting, $attendee);
        $highlights = $this->buildHighlights($content);
        $prepMinutes = $this->estimatePrepTime($content);

        return MeetingPrepBrief::create([
            'minutes_of_meeting_id' => $meeting->id,
            'attendee_id' => $attendee->id,
            'user_id' => $attendee->user_id,
            'content' => $content,
            'summary_highlights' => $highlights,
            'estimated_prep_minutes' => $prepMinutes,
            'generated_at' => now(),
        ]);
    }

    /**
     * Build the full brief content structure.
     *
     * @return array<string, mixed>
     */
    private function buildBriefContent(MinutesOfMeeting $meeting, MomAttendee $attendee): array
    {
        $actionItems = $this->getAttendeeActionItems($meeting, $attendee);
        $previousMeetings = $this->getPreviousMeetingContext($meeting);
        $unresolvedItems = $this->getUnresolvedItems($meeting);
        $readingList = $this->getReadingList($meeting);
        $metrics = $this->getGovernanceMetrics($meeting, $attendee);
        $aiInsights = $this->generateAiInsights($meeting, $attendee, $previousMeetings);

        return [
            'executive_summary' => $aiInsights['executive_summary'] ?? '',
            'action_items' => $actionItems,
            'unresolved_items' => $unresolvedItems,
            'agenda_deep_dive' => $aiInsights['suggested_questions'] ?? [],
            'metrics' => $metrics,
            'reading_list' => $readingList,
            'conflicts' => [],
        ];
    }

    /**
     * Get action items relevant to this attendee.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function getAttendeeActionItems(MinutesOfMeeting $meeting, MomAttendee $attendee): array
    {
        if (! $attendee->user_id) {
            return ['overdue' => [], 'pending' => [], 'completed' => []];
        }

        $query = ActionItem::query()
            ->where('organization_id', $meeting->organization_id)
            ->where('assigned_to', $attendee->user_id);

        $overdue = (clone $query)
            ->whereIn('status', [ActionItemStatus::Open, ActionItemStatus::InProgress])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->get()
            ->map(fn (ActionItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'due_date' => $item->due_date?->toDateString(),
                'priority' => $item->priority->value,
                'days_overdue' => (int) $item->due_date?->diffInDays(now()),
            ])
            ->all();

        $pending = (clone $query)
            ->whereIn('status', [ActionItemStatus::Open, ActionItemStatus::InProgress])
            ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '>=', now()))
            ->get()
            ->map(fn (ActionItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'due_date' => $item->due_date?->toDateString(),
                'priority' => $item->priority->value,
            ])
            ->all();

        $completed = (clone $query)
            ->where('status', ActionItemStatus::Completed)
            ->where('completed_at', '>=', now()->subMonth())
            ->latest('completed_at')
            ->limit(5)
            ->get()
            ->map(fn (ActionItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'completed_at' => $item->completed_at?->toDateString(),
            ])
            ->all();

        return compact('overdue', 'pending', 'completed');
    }

    /**
     * Get context from previous meetings in same project/series.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getPreviousMeetingContext(MinutesOfMeeting $meeting): array
    {
        $query = MinutesOfMeeting::query()
            ->where('organization_id', $meeting->organization_id)
            ->where('id', '!=', $meeting->id)
            ->whereIn('status', [MeetingStatus::Finalized, MeetingStatus::Approved])
            ->latest('meeting_date')
            ->limit(5);

        if ($meeting->project_id) {
            $query->where('project_id', $meeting->project_id);
        } elseif ($meeting->meeting_series_id) {
            $query->where('meeting_series_id', $meeting->meeting_series_id);
        }

        return $query->get()
            ->map(function (MinutesOfMeeting $prev) {
                $summary = MomExtraction::where('minutes_of_meeting_id', $prev->id)
                    ->where('type', 'summary')
                    ->first();
                $decisions = MomExtraction::where('minutes_of_meeting_id', $prev->id)
                    ->where('type', 'decisions')
                    ->first();

                return [
                    'id' => $prev->id,
                    'title' => $prev->title,
                    'meeting_date' => $prev->meeting_date?->toDateString(),
                    'summary' => $summary?->content ?? '',
                    'decisions' => $decisions?->structured_data ?? [],
                ];
            })
            ->all();
    }

    /**
     * Get unresolved items from previous meetings.
     *
     * @return list<array<string, mixed>>
     */
    private function getUnresolvedItems(MinutesOfMeeting $meeting): array
    {
        return ActionItem::query()
            ->where('organization_id', $meeting->organization_id)
            ->where('status', ActionItemStatus::CarriedForward)
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (ActionItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'from_meeting' => $item->meeting?->title,
                'created_at' => $item->created_at->toDateString(),
            ])
            ->all();
    }

    /**
     * Get documents attached to the meeting with estimated reading time.
     *
     * @return list<array<string, mixed>>
     */
    private function getReadingList(MinutesOfMeeting $meeting): array
    {
        if (! $meeting->relationLoaded('documents')) {
            $meeting->load('documents');
        }

        return $meeting->documents
            ->map(function ($doc) {
                $sizeMb = ($doc->file_size ?? 0) / (1024 * 1024);
                $estimatedPages = max(1, (int) ceil($sizeMb * 4));
                $estimatedMinutes = max(1, (int) ceil($estimatedPages * 1.5));

                return [
                    'id' => $doc->id,
                    'filename' => $doc->original_filename ?? $doc->title ?? 'Document',
                    'file_size' => $doc->file_size ?? 0,
                    'estimated_pages' => $estimatedPages,
                    'estimated_minutes' => $estimatedMinutes,
                ];
            })
            ->all();
    }

    /**
     * Calculate governance metrics.
     *
     * @return array<string, mixed>
     */
    private function getGovernanceMetrics(MinutesOfMeeting $meeting, MomAttendee $attendee): array
    {
        $orgId = $meeting->organization_id;

        $totalMeetings = MinutesOfMeeting::where('organization_id', $orgId)
            ->whereIn('status', [MeetingStatus::Finalized, MeetingStatus::Approved])
            ->where('meeting_date', '>=', now()->subMonths(6))
            ->count();

        $attendedCount = 0;
        if ($attendee->user_id) {
            $attendedCount = MomAttendee::whereHas('meeting', fn ($q) => $q
                ->where('organization_id', $orgId)
                ->whereIn('status', [MeetingStatus::Finalized, MeetingStatus::Approved])
                ->where('meeting_date', '>=', now()->subMonths(6))
            )
                ->where('user_id', $attendee->user_id)
                ->where('is_present', true)
                ->count();
        }

        $totalActionItems = ActionItem::where('organization_id', $orgId)
            ->where('created_at', '>=', now()->subMonths(6))
            ->count();

        $completedActionItems = ActionItem::where('organization_id', $orgId)
            ->where('status', ActionItemStatus::Completed)
            ->where('created_at', '>=', now()->subMonths(6))
            ->count();

        return [
            'attendance_rate' => $totalMeetings > 0 ? round(($attendedCount / $totalMeetings) * 100) : 0,
            'total_meetings_6m' => $totalMeetings,
            'action_completion_rate' => $totalActionItems > 0 ? round(($completedActionItems / $totalActionItems) * 100) : 0,
        ];
    }

    /**
     * Generate AI insights (executive summary, suggested questions, reading priorities).
     *
     * @param array<int, array<string, mixed>> $previousMeetings
     * @return array<string, mixed>
     */
    private function generateAiInsights(MinutesOfMeeting $meeting, MomAttendee $attendee, array $previousMeetings): array
    {
        try {
            $provider = $this->aiProviderFactory->make(
                $meeting->organization?->ai_provider,
                $meeting->organization?->ai_model,
            );

            $context = $this->buildAiContext($meeting, $attendee, $previousMeetings);

            $response = $provider->chat(
                systemMessage: 'You are a meeting preparation assistant. Generate a concise executive summary and 3-5 suggested questions for an upcoming meeting. Respond in JSON format with keys: executive_summary (string), suggested_questions (array of strings), reading_priorities (array of strings).',
                userMessage: $context,
            );

            $parsed = json_decode($response->content, true);

            return is_array($parsed) ? $parsed : [
                'executive_summary' => $response->content,
                'suggested_questions' => [],
                'reading_priorities' => [],
            ];
        } catch (\Throwable) {
            return [
                'executive_summary' => "Upcoming meeting: {$meeting->title}",
                'suggested_questions' => [],
                'reading_priorities' => [],
            ];
        }
    }

    /**
     * Build context string for AI prompt.
     */
    private function buildAiContext(MinutesOfMeeting $meeting, MomAttendee $attendee, array $previousMeetings): string
    {
        $parts = [];
        $parts[] = "Meeting: {$meeting->title}";
        $parts[] = "Date: {$meeting->meeting_date?->toDateString()}";
        $parts[] = "Type: {$meeting->meeting_type?->value}";
        $parts[] = "Attendee Role: {$attendee->role?->value}";

        if ($meeting->content) {
            $parts[] = "Agenda/Content: " . mb_substr($meeting->content, 0, 1500);
        }

        $agenda = $meeting->metadata['agenda'] ?? [];
        if ($agenda) {
            $parts[] = "Agenda Items: " . implode(', ', $agenda);
        }

        foreach (array_slice($previousMeetings, 0, 3) as $prev) {
            $parts[] = "Previous Meeting ({$prev['meeting_date']}): {$prev['title']}";
            if ($prev['summary']) {
                $parts[] = "Summary: " . mb_substr($prev['summary'], 0, 500);
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Build top 3 highlights for email digest.
     *
     * @param array<string, mixed> $content
     * @return list<string>
     */
    private function buildHighlights(array $content): array
    {
        $highlights = [];

        $overdueCount = count($content['action_items']['overdue'] ?? []);
        if ($overdueCount > 0) {
            $highlights[] = "You have {$overdueCount} overdue action item(s) to address.";
        }

        $unresolvedCount = count($content['unresolved_items'] ?? []);
        if ($unresolvedCount > 0) {
            $highlights[] = "{$unresolvedCount} item(s) carried forward from previous meetings.";
        }

        $readingCount = count($content['reading_list'] ?? []);
        if ($readingCount > 0) {
            $totalMinutes = collect($content['reading_list'])->sum('estimated_minutes');
            $highlights[] = "{$readingCount} document(s) to review (~{$totalMinutes} min reading time).";
        }

        if (empty($highlights)) {
            $highlights[] = $content['executive_summary'] ?: 'Meeting preparation brief is ready.';
        }

        return array_slice($highlights, 0, 3);
    }

    /**
     * Estimate total preparation time in minutes.
     *
     * @param array<string, mixed> $content
     */
    private function estimatePrepTime(array $content): int
    {
        $readingTime = collect($content['reading_list'] ?? [])->sum('estimated_minutes');
        $actionItemReviewTime = count($content['action_items']['overdue'] ?? []) * 2;
        $summaryReviewTime = 5;

        return (int) ($readingTime + $actionItemReviewTime + $summaryReviewTime);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=MeetingPrepBriefServiceTest`
Expected: All tests PASS

**Step 5: Commit**

```bash
git add app/Domain/AI/Services/MeetingPrepBriefService.php tests/Feature/Domain/AI/Services/MeetingPrepBriefServiceTest.php
git commit -m "feat: add MeetingPrepBriefService with personalized brief generation"
```

---

### Task 4: GeneratePrepBriefsJob + Scheduler

**Files:**
- Create: `app/Domain/AI/Jobs/GeneratePrepBriefsJob.php`
- Modify: `routes/console.php` (add schedule)
- Test: `tests/Feature/Domain/AI/Jobs/GeneratePrepBriefsJobTest.php`

**Step 1: Write the failing test**

Create test: `php artisan make:test --pest Domain/AI/Jobs/GeneratePrepBriefsJobTest --no-interaction`

```php
<?php

use App\Domain\AI\Jobs\GeneratePrepBriefsJob;
use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\Organization;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => json_encode([
                    'executive_summary' => 'Test summary',
                    'suggested_questions' => [],
                    'reading_priorities' => [],
                ])]],
            ],
            'usage' => ['total_tokens' => 100],
        ]),
    ]);
    Notification::fake();
});

it('generates briefs for meetings happening in the next 24 hours', function () {
    $org = Organization::factory()->create();
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'status' => MeetingStatus::Draft,
        'meeting_date' => now()->addHours(20),
    ]);
    MomAttendee::factory()->count(2)->create([
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    (new GeneratePrepBriefsJob)->handle();

    expect(MeetingPrepBrief::count())->toBe(2);
});

it('skips meetings more than 24 hours away', function () {
    $org = Organization::factory()->create();
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'status' => MeetingStatus::Draft,
        'meeting_date' => now()->addDays(3),
    ]);
    MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    (new GeneratePrepBriefsJob)->handle();

    expect(MeetingPrepBrief::count())->toBe(0);
});

it('skips finalized and approved meetings', function () {
    $org = Organization::factory()->create();
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'status' => MeetingStatus::Approved,
        'meeting_date' => now()->addHours(20),
    ]);
    MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    (new GeneratePrepBriefsJob)->handle();

    expect(MeetingPrepBrief::count())->toBe(0);
});

it('skips meetings with no attendees', function () {
    $org = Organization::factory()->create();
    MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'status' => MeetingStatus::Draft,
        'meeting_date' => now()->addHours(20),
    ]);

    (new GeneratePrepBriefsJob)->handle();

    expect(MeetingPrepBrief::count())->toBe(0);
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=GeneratePrepBriefsJobTest`
Expected: FAIL — class not found

**Step 3: Create the job**

```php
<?php

namespace App\Domain\AI\Jobs;

use App\Domain\AI\Notifications\MeetingPrepBriefNotification;
use App\Domain\AI\Services\MeetingPrepBriefService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\MeetingStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePrepBriefsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 120;

    public function handle(): void
    {
        $meetings = MinutesOfMeeting::query()
            ->whereIn('status', [MeetingStatus::Draft, MeetingStatus::InProgress])
            ->whereNotNull('meeting_date')
            ->whereBetween('meeting_date', [now(), now()->addHours(24)])
            ->has('attendees')
            ->with('attendees.user')
            ->get();

        $service = app(MeetingPrepBriefService::class);

        foreach ($meetings as $meeting) {
            try {
                $briefs = $service->generateForMeeting($meeting);

                foreach ($briefs as $brief) {
                    if ($brief->user) {
                        $brief->user->notify(new MeetingPrepBriefNotification($brief));
                        $brief->update(['email_sent_at' => now()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Failed to generate prep briefs for meeting {$meeting->id}: {$e->getMessage()}");
            }
        }
    }
}
```

**Step 4: Register in scheduler**

Modify `routes/console.php` — add after existing schedules:

```php
Schedule::job(new \App\Domain\AI\Jobs\GeneratePrepBriefsJob)->dailyAt('08:00');
```

**Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=GeneratePrepBriefsJobTest`
Expected: All tests PASS (MeetingPrepBriefNotification may need to be created first — create a stub if needed)

**Step 6: Commit**

```bash
git add app/Domain/AI/Jobs/GeneratePrepBriefsJob.php routes/console.php tests/Feature/Domain/AI/Jobs/GeneratePrepBriefsJobTest.php
git commit -m "feat: add GeneratePrepBriefsJob with daily scheduler"
```

---

### Task 5: MeetingPrepBriefNotification

**Files:**
- Create: `app/Domain/AI/Notifications/MeetingPrepBriefNotification.php`
- Create: `resources/views/emails/prep-brief.blade.php`
- Test: `tests/Feature/Domain/AI/Notifications/MeetingPrepBriefNotificationTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\AI\Notifications\MeetingPrepBriefNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;

uses(RefreshDatabase::class);

it('sends via mail and database channels', function () {
    $brief = MeetingPrepBrief::factory()->create();
    $notification = new MeetingPrepBriefNotification($brief);

    expect($notification->via($brief->user))->toContain('mail')
        ->and($notification->via($brief->user))->toContain('database');
});

it('builds a mail message with meeting details', function () {
    $brief = MeetingPrepBrief::factory()->create([
        'summary_highlights' => ['You have 2 overdue items', '3 documents to review'],
        'estimated_prep_minutes' => 25,
    ]);

    $notification = new MeetingPrepBriefNotification($brief);
    $mail = $notification->toMail($brief->user);

    expect($mail)->toBeInstanceOf(MailMessage::class)
        ->and($mail->subject)->toContain('Meeting Prep');
});

it('returns array data for database notification', function () {
    $brief = MeetingPrepBrief::factory()->create();
    $notification = new MeetingPrepBriefNotification($brief);

    $data = $notification->toArray($brief->user);

    expect($data)->toHaveKeys(['type', 'meeting_prep_brief_id', 'meeting_id'])
        ->and($data['type'])->toBe('meeting_prep_brief');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=MeetingPrepBriefNotificationTest`
Expected: FAIL — class not found

**Step 3: Create the notification**

```php
<?php

namespace App\Domain\AI\Notifications;

use App\Domain\AI\Models\MeetingPrepBrief;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingPrepBriefNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MeetingPrepBrief $brief,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->brief->meeting;
        $highlights = $this->brief->summary_highlights ?? [];
        $prepTime = $this->brief->estimated_prep_minutes;

        $mail = (new MailMessage)
            ->subject("Meeting Prep Brief: {$meeting->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your prep brief for **{$meeting->title}** is ready.")
            ->line("Meeting Date: {$meeting->meeting_date?->format('d M Y, h:i A')}");

        foreach ($highlights as $highlight) {
            $mail->line("- {$highlight}");
        }

        if ($prepTime > 0) {
            $mail->line("Estimated prep time: {$prepTime} minutes");
        }

        $mail->action('View Full Brief', route('meetings.prep-brief', $meeting));

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'meeting_prep_brief',
            'meeting_prep_brief_id' => $this->brief->id,
            'meeting_id' => $this->brief->minutes_of_meeting_id,
            'meeting_title' => $this->brief->meeting?->title,
            'estimated_prep_minutes' => $this->brief->estimated_prep_minutes,
        ];
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=MeetingPrepBriefNotificationTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Domain/AI/Notifications/MeetingPrepBriefNotification.php tests/Feature/Domain/AI/Notifications/MeetingPrepBriefNotificationTest.php
git commit -m "feat: add MeetingPrepBriefNotification with email and database channels"
```

---

### Task 6: PrepBriefController + Routes

**Files:**
- Create: `app/Domain/AI/Controllers/PrepBriefController.php`
- Modify: `routes/web.php` — add routes under meetings prefix
- Test: `tests/Feature/Domain/AI/Controllers/PrepBriefControllerTest.php`

**Step 1: Write the failing test**

```php
<?php

use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\Organization;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('shows the prep brief for the authenticated user', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'status' => MeetingStatus::Draft,
    ]);
    $attendee = MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'user_id' => $user->id,
    ]);
    $brief = MeetingPrepBrief::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'attendee_id' => $attendee->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('meetings.prep-brief', $meeting));

    $response->assertOk();
    $response->assertViewHas('brief');
});

it('marks brief as viewed when accessed', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
    ]);
    $attendee = MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'user_id' => $user->id,
    ]);
    $brief = MeetingPrepBrief::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'attendee_id' => $attendee->id,
        'user_id' => $user->id,
        'viewed_at' => null,
    ]);

    $this->actingAs($user)->get(route('meetings.prep-brief', $meeting));

    expect($brief->fresh()->viewed_at)->not->toBeNull();
});

it('can manually trigger brief generation', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['executive_summary' => 'Test', 'suggested_questions' => [], 'reading_priorities' => []])]]],
            'usage' => ['total_tokens' => 100],
        ]),
    ]);

    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'status' => MeetingStatus::Draft,
        'meeting_date' => now()->addDay(),
    ]);
    MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('meetings.prep-brief.generate', $meeting));

    $response->assertRedirect();
    expect(MeetingPrepBrief::count())->toBeGreaterThan(0);
});

it('tracks section reads via JSON endpoint', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $org->id]);
    $attendee = MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'user_id' => $user->id,
    ]);
    $brief = MeetingPrepBrief::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'attendee_id' => $attendee->id,
        'user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->postJson(
        route('meetings.prep-brief.section-read', [$meeting, $brief]),
        ['section' => 'executive_summary'],
    );

    $response->assertOk();
    expect($brief->fresh()->sections_read)->toContain('executive_summary');
});
```

**Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=PrepBriefControllerTest`
Expected: FAIL — route/class not found

**Step 3: Create the controller**

```php
<?php

namespace App\Domain\AI\Controllers;

use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\AI\Services\MeetingPrepBriefService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class PrepBriefController extends Controller
{
    public function __construct(
        private MeetingPrepBriefService $briefService,
    ) {}

    public function show(Request $request, MinutesOfMeeting $meeting): View
    {
        $brief = MeetingPrepBrief::where('minutes_of_meeting_id', $meeting->id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($brief && ! $brief->viewed_at) {
            $brief->markAsViewed();
        }

        return view('meetings.prep-brief', [
            'meeting' => $meeting->load('attendees', 'documents'),
            'brief' => $brief,
        ]);
    }

    public function generate(Request $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->briefService->generateForMeeting($meeting);

        return back()->with('success', 'Meeting prep briefs generated successfully.');
    }

    public function markSectionRead(Request $request, MinutesOfMeeting $meeting, MeetingPrepBrief $brief): JsonResponse
    {
        $validated = $request->validate([
            'section' => ['required', 'string', 'max:50'],
        ]);

        $brief->markSectionRead($validated['section']);

        return response()->json(['status' => 'ok']);
    }
}
```

**Step 4: Add routes**

Modify `routes/web.php` — inside the `meetings/{meeting}` prefix group, add:

```php
Route::get('prep-brief', [\App\Domain\AI\Controllers\PrepBriefController::class, 'show'])->name('prep-brief');
Route::post('prep-brief/generate', [\App\Domain\AI\Controllers\PrepBriefController::class, 'generate'])->name('prep-brief.generate');
Route::post('prep-brief/{brief}/section-read', [\App\Domain\AI\Controllers\PrepBriefController::class, 'markSectionRead'])->name('prep-brief.section-read');
```

**Step 5: Run test to verify it passes**

Run: `php artisan test --compact --filter=PrepBriefControllerTest`
Expected: PASS (may need to create a minimal view first)

**Step 6: Commit**

```bash
git add app/Domain/AI/Controllers/PrepBriefController.php routes/web.php tests/Feature/Domain/AI/Controllers/PrepBriefControllerTest.php
git commit -m "feat: add PrepBriefController with show, generate, and section tracking"
```

---

### Task 7: Prep Brief Blade View

**Files:**
- Create: `resources/views/meetings/prep-brief.blade.php`

**Step 1: Create the Blade view**

Reference `resources/views/meetings/tabs/ai-extraction.blade.php` for styling patterns. Build a clean, collapsible-section layout:

```blade
@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto" x-data="prepBrief(@js($brief?->content ?? []), @js($brief?->id), @js($meeting->id))">
    {{-- Header --}}
    <div class="mb-6">
        <a href="{{ route('meetings.show', $meeting) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">&larr; Back to Meeting</a>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white mt-2">Meeting Prep Brief</h1>
        <p class="text-slate-500 dark:text-slate-400">{{ $meeting->title }} &mdash; {{ $meeting->meeting_date?->format('d M Y, h:i A') }}</p>
        @if($brief)
            <p class="text-sm text-slate-400 mt-1">Generated {{ $brief->generated_at->diffForHumans() }} &middot; Est. {{ $brief->estimated_prep_minutes }} min prep time</p>
        @endif
    </div>

    @if(!$brief)
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow p-8 text-center">
            <p class="text-slate-500 dark:text-slate-400 mb-4">No prep brief generated yet.</p>
            <form method="POST" action="{{ route('meetings.prep-brief.generate', $meeting) }}">
                @csrf
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    Generate Prep Brief
                </button>
            </form>
        </div>
    @else
        {{-- Executive Summary --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow mb-4" x-data="{ open: true }">
            <button @click="open = !open; if(open) markRead('executive_summary')" class="w-full px-6 py-4 flex justify-between items-center text-left">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Executive Summary</h2>
                <svg :class="{ 'rotate-180': open }" class="w-5 h-5 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-transition class="px-6 pb-4">
                <p class="text-slate-600 dark:text-slate-300" x-text="content.executive_summary || 'No summary available.'"></p>
            </div>
        </div>

        {{-- Action Items --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow mb-4" x-data="{ open: true }">
            <button @click="open = !open; if(open) markRead('action_items')" class="w-full px-6 py-4 flex justify-between items-center text-left">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    Your Action Items
                    <template x-if="(content.action_items?.overdue?.length || 0) > 0">
                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800" x-text="content.action_items.overdue.length + ' overdue'"></span>
                    </template>
                </h2>
                <svg :class="{ 'rotate-180': open }" class="w-5 h-5 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-transition class="px-6 pb-4 space-y-3">
                {{-- Overdue --}}
                <template x-if="content.action_items?.overdue?.length > 0">
                    <div>
                        <h3 class="text-sm font-medium text-red-600 mb-2">Overdue</h3>
                        <template x-for="item in content.action_items.overdue" :key="item.id">
                            <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                                <span class="text-sm text-slate-700 dark:text-slate-300" x-text="item.title"></span>
                                <span class="text-xs text-red-500" x-text="item.days_overdue + ' days overdue'"></span>
                            </div>
                        </template>
                    </div>
                </template>
                {{-- Pending --}}
                <template x-if="content.action_items?.pending?.length > 0">
                    <div>
                        <h3 class="text-sm font-medium text-amber-600 mb-2">Pending</h3>
                        <template x-for="item in content.action_items.pending" :key="item.id">
                            <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                                <span class="text-sm text-slate-700 dark:text-slate-300" x-text="item.title"></span>
                                <span class="text-xs text-slate-400" x-text="item.due_date || 'No due date'"></span>
                            </div>
                        </template>
                    </div>
                </template>
                {{-- Completed --}}
                <template x-if="content.action_items?.completed?.length > 0">
                    <div>
                        <h3 class="text-sm font-medium text-green-600 mb-2">Recently Completed</h3>
                        <template x-for="item in content.action_items.completed" :key="item.id">
                            <div class="py-2 border-b border-slate-100 dark:border-slate-700">
                                <span class="text-sm text-slate-500 line-through" x-text="item.title"></span>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Metrics --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow mb-4" x-data="{ open: true }">
            <button @click="open = !open; if(open) markRead('metrics')" class="w-full px-6 py-4 flex justify-between items-center text-left">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Key Metrics</h2>
                <svg :class="{ 'rotate-180': open }" class="w-5 h-5 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-transition class="px-6 pb-4">
                <div class="grid grid-cols-3 gap-4">
                    <div class="text-center p-3 bg-slate-50 dark:bg-slate-700 rounded-lg">
                        <p class="text-2xl font-bold text-indigo-600" x-text="(content.metrics?.attendance_rate || 0) + '%'"></p>
                        <p class="text-xs text-slate-500">Attendance Rate</p>
                    </div>
                    <div class="text-center p-3 bg-slate-50 dark:bg-slate-700 rounded-lg">
                        <p class="text-2xl font-bold text-green-600" x-text="(content.metrics?.action_completion_rate || 0) + '%'"></p>
                        <p class="text-xs text-slate-500">Action Completion</p>
                    </div>
                    <div class="text-center p-3 bg-slate-50 dark:bg-slate-700 rounded-lg">
                        <p class="text-2xl font-bold text-slate-700 dark:text-slate-300" x-text="content.metrics?.total_meetings_6m || 0"></p>
                        <p class="text-xs text-slate-500">Meetings (6 mo)</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Reading List --}}
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow mb-4" x-data="{ open: true }">
            <button @click="open = !open; if(open) markRead('reading_list')" class="w-full px-6 py-4 flex justify-between items-center text-left">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Reading List</h2>
                <svg :class="{ 'rotate-180': open }" class="w-5 h-5 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" x-transition class="px-6 pb-4">
                <template x-if="content.reading_list?.length > 0">
                    <div class="space-y-2">
                        <template x-for="doc in content.reading_list" :key="doc.id">
                            <div class="flex justify-between items-center py-2 border-b border-slate-100 dark:border-slate-700">
                                <span class="text-sm text-slate-700 dark:text-slate-300" x-text="doc.filename"></span>
                                <span class="text-xs text-slate-400" x-text="'~' + doc.estimated_pages + ' pages, ' + doc.estimated_minutes + ' min'"></span>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="!content.reading_list?.length">
                    <p class="text-sm text-slate-400">No documents attached to this meeting.</p>
                </template>
            </div>
        </div>

        {{-- Suggested Questions --}}
        <template x-if="content.agenda_deep_dive?.length > 0">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow mb-4" x-data="{ open: true }">
                <button @click="open = !open; if(open) markRead('agenda_deep_dive')" class="w-full px-6 py-4 flex justify-between items-center text-left">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Suggested Questions</h2>
                    <svg :class="{ 'rotate-180': open }" class="w-5 h-5 text-slate-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-transition class="px-6 pb-4">
                    <ul class="space-y-2">
                        <template x-for="(q, i) in content.agenda_deep_dive" :key="i">
                            <li class="flex items-start gap-2">
                                <span class="text-indigo-500 mt-0.5">?</span>
                                <span class="text-sm text-slate-600 dark:text-slate-300" x-text="q"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </template>
    @endif
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('prepBrief', (content, briefId, meetingId) => ({
        content: content || {},
        briefId: briefId,
        meetingId: meetingId,

        markRead(section) {
            if (!this.briefId) return;
            fetch(`/meetings/${this.meetingId}/prep-brief/${this.briefId}/section-read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ section }),
            });
        },
    }));
});
</script>
@endpush
@endsection
```

**Step 2: Run all Prep Brief tests to verify everything still passes**

Run: `php artisan test --compact --filter=PrepBrief`
Expected: All PASS

**Step 3: Commit**

```bash
git add resources/views/meetings/prep-brief.blade.php
git commit -m "feat: add prep brief Blade view with collapsible sections and section tracking"
```

---

### Task 8: Run Pint + Full Test Suite for Part A

**Step 1: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

Fix any style issues.

**Step 2: Run full test suite**

Run: `php artisan test --compact`
Expected: All tests pass, no regressions.

**Step 3: Commit any Pint fixes**

```bash
git add -A
git commit -m "style: apply Pint formatting to prep brief feature"
```

---

## Part B: Live Meeting AI Dashboard

### Task 9: Database Migrations — Live Meeting Tables

**Files:**
- Create: `database/migrations/2026_03_08_110000_create_live_meeting_sessions_table.php`
- Create: `database/migrations/2026_03_08_110001_create_live_transcript_chunks_table.php`

**Step 1: Create migrations**

Run: `php artisan make:migration create_live_meeting_sessions_table --no-interaction && php artisan make:migration create_live_transcript_chunks_table --no-interaction`

**`live_meeting_sessions` migration:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_meeting_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('started_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->json('config')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('total_duration_seconds')->nullable();
            $table->timestamps();

            $table->index(['minutes_of_meeting_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_meeting_sessions');
    }
};
```

**`live_transcript_chunks` migration:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_transcript_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('live_meeting_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_number');
            $table->string('audio_file_path')->nullable();
            $table->text('text')->nullable();
            $table->string('speaker')->nullable();
            $table->double('start_time')->default(0);
            $table->double('end_time')->default(0);
            $table->double('confidence')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['live_meeting_session_id', 'chunk_number']);
            $table->index(['live_meeting_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_transcript_chunks');
    }
};
```

**Step 2: Run migration**

Run: `php artisan migrate --no-interaction`
Expected: Both tables created.

**Step 3: Commit**

```bash
git add database/migrations/*_create_live_meeting_sessions_table.php database/migrations/*_create_live_transcript_chunks_table.php
git commit -m "feat: add live_meeting_sessions and live_transcript_chunks migrations"
```

---

### Task 10: LiveMeetingSession + LiveTranscriptChunk Models

**Files:**
- Create: `app/Domain/LiveMeeting/Models/LiveMeetingSession.php`
- Create: `app/Domain/LiveMeeting/Models/LiveTranscriptChunk.php`
- Create: `app/Domain/LiveMeeting/Enums/LiveSessionStatus.php`
- Create: `app/Domain/LiveMeeting/Enums/ChunkStatus.php`
- Test: `tests/Feature/Domain/LiveMeeting/Models/LiveMeetingSessionTest.php`

**Step 1: Create enums**

`app/Domain/LiveMeeting/Enums/LiveSessionStatus.php`:
```php
<?php

namespace App\Domain\LiveMeeting\Enums;

enum LiveSessionStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';
}
```

`app/Domain/LiveMeeting/Enums/ChunkStatus.php`:
```php
<?php

namespace App\Domain\LiveMeeting\Enums;

enum ChunkStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
```

**Step 2: Write the failing test**

```php
<?php

use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('belongs to a meeting', function () {
    $session = LiveMeetingSession::factory()->create();
    expect($session->meeting)->toBeInstanceOf(MinutesOfMeeting::class);
});

it('belongs to the user who started it', function () {
    $session = LiveMeetingSession::factory()->create();
    expect($session->startedBy)->toBeInstanceOf(User::class);
});

it('has many chunks', function () {
    $session = LiveMeetingSession::factory()->create();
    LiveTranscriptChunk::factory()->count(3)->create([
        'live_meeting_session_id' => $session->id,
    ]);

    expect($session->chunks)->toHaveCount(3);
});

it('casts status as LiveSessionStatus enum', function () {
    $session = LiveMeetingSession::factory()->create(['status' => 'active']);
    expect($session->status)->toBe(LiveSessionStatus::Active);
});

it('gets completed transcript text', function () {
    $session = LiveMeetingSession::factory()->create();
    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => 'First chunk.',
        'status' => ChunkStatus::Completed,
    ]);
    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 2,
        'text' => 'Second chunk.',
        'status' => ChunkStatus::Completed,
    ]);
    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 3,
        'text' => null,
        'status' => ChunkStatus::Pending,
    ]);

    expect($session->getCompletedTranscriptText())->toBe("First chunk.\nSecond chunk.");
});

it('checks if session is active', function () {
    $active = LiveMeetingSession::factory()->create(['status' => LiveSessionStatus::Active]);
    $ended = LiveMeetingSession::factory()->create(['status' => LiveSessionStatus::Ended]);

    expect($active->isActive())->toBeTrue()
        ->and($ended->isActive())->toBeFalse();
});
```

**Step 3: Run test to verify it fails**

Run: `php artisan test --compact --filter=LiveMeetingSessionTest`
Expected: FAIL

**Step 4: Create models**

`app/Domain/LiveMeeting/Models/LiveMeetingSession.php`:
```php
<?php

namespace App\Domain\LiveMeeting\Models;

use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LiveMeetingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'minutes_of_meeting_id',
        'started_by',
        'status',
        'config',
        'started_at',
        'paused_at',
        'ended_at',
        'total_duration_seconds',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LiveSessionStatus::class,
            'config' => 'array',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(LiveTranscriptChunk::class)->orderBy('chunk_number');
    }

    public function getCompletedTranscriptText(): string
    {
        return $this->chunks()
            ->where('status', 'completed')
            ->whereNotNull('text')
            ->orderBy('chunk_number')
            ->pluck('text')
            ->implode("\n");
    }

    public function isActive(): bool
    {
        return $this->status === LiveSessionStatus::Active;
    }
}
```

`app/Domain/LiveMeeting/Models/LiveTranscriptChunk.php`:
```php
<?php

namespace App\Domain\LiveMeeting\Models;

use App\Domain\LiveMeeting\Enums\ChunkStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveTranscriptChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_meeting_session_id',
        'chunk_number',
        'audio_file_path',
        'text',
        'speaker',
        'start_time',
        'end_time',
        'confidence',
        'status',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChunkStatus::class,
            'start_time' => 'double',
            'end_time' => 'double',
            'confidence' => 'double',
            'chunk_number' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveMeetingSession::class, 'live_meeting_session_id');
    }
}
```

**Step 5: Create factories for both models** — follow existing factory patterns.

**Step 6: Run test to verify it passes**

Run: `php artisan test --compact --filter=LiveMeetingSessionTest`
Expected: PASS

**Step 7: Commit**

```bash
git add app/Domain/LiveMeeting/ database/factories/ tests/Feature/Domain/LiveMeeting/
git commit -m "feat: add LiveMeetingSession and LiveTranscriptChunk models with enums"
```

---

### Task 11: LiveMeetingService

**Files:**
- Create: `app/Domain/LiveMeeting/Services/LiveMeetingService.php`
- Test: `tests/Feature/Domain/LiveMeeting/Services/LiveMeetingServiceTest.php`

**Step 1: Write the failing test**

Test should cover: startSession, endSession (merges chunks), processChunk (stores audio + dispatches job), getSessionState.

Key test cases:
- Starting a session creates a LiveMeetingSession record with status Active
- Starting a session when one already exists throws/returns error
- Ending a session sets status to Ended, merges chunks into AudioTranscription
- processChunk creates a LiveTranscriptChunk record and dispatches LiveTranscriptionJob

**Step 2: Create the service**

Reference `TranscriptionService` for chunk/transcription handling patterns. The service orchestrates:
- `startSession(MinutesOfMeeting, User): LiveMeetingSession`
- `endSession(LiveMeetingSession): void` — changes status, calculates duration, calls merge logic
- `processChunk(LiveMeetingSession, UploadedFile, int chunkNumber, float startTime, float endTime): LiveTranscriptChunk`
- `getSessionState(LiveMeetingSession): array` — returns current chunks + latest extraction data

**Step 3: Run tests, commit**

```bash
git add app/Domain/LiveMeeting/Services/LiveMeetingService.php tests/Feature/Domain/LiveMeeting/Services/LiveMeetingServiceTest.php
git commit -m "feat: add LiveMeetingService with session lifecycle management"
```

---

### Task 12: Broadcast Events

**Files:**
- Create: `app/Domain/LiveMeeting/Events/TranscriptionChunkProcessed.php`
- Create: `app/Domain/LiveMeeting/Events/LiveExtractionUpdated.php`
- Create: `app/Domain/LiveMeeting/Events/LiveSessionStarted.php`
- Create: `app/Domain/LiveMeeting/Events/LiveSessionEnded.php`
- Modify: `routes/channels.php` — add live-meeting channel

**Step 1: Create events**

Each event implements `ShouldBroadcast` and broadcasts on `PrivateChannel('live-meeting.{sessionId}')`.

Pattern from existing events — reference `TranscriptionCompleted`:

```php
<?php

namespace App\Domain\LiveMeeting\Events;

use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranscriptionChunkProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LiveTranscriptChunk $chunk,
    ) {}

    /**
     * @return list<\Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('live-meeting.' . $this->chunk->live_meeting_session_id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'chunk_id' => $this->chunk->id,
            'chunk_number' => $this->chunk->chunk_number,
            'text' => $this->chunk->text,
            'speaker' => $this->chunk->speaker,
            'start_time' => $this->chunk->start_time,
            'end_time' => $this->chunk->end_time,
            'confidence' => $this->chunk->confidence,
        ];
    }
}
```

Create similar events for `LiveExtractionUpdated`, `LiveSessionStarted`, `LiveSessionEnded`.

**Step 2: Add channel to `routes/channels.php`**

```php
Broadcast::channel('live-meeting.{sessionId}', function (User $user, int $sessionId) {
    $session = \App\Domain\LiveMeeting\Models\LiveMeetingSession::find($sessionId);

    return $session && $session->meeting->organization_id === $user->current_organization_id;
});
```

**Step 3: Commit**

```bash
git add app/Domain/LiveMeeting/Events/ routes/channels.php
git commit -m "feat: add live meeting broadcast events and channel authorization"
```

---

### Task 13: LiveTranscriptionJob + LiveExtractionJob

**Files:**
- Create: `app/Domain/LiveMeeting/Jobs/LiveTranscriptionJob.php`
- Create: `app/Domain/LiveMeeting/Jobs/LiveExtractionJob.php`
- Test: `tests/Feature/Domain/LiveMeeting/Jobs/LiveTranscriptionJobTest.php`
- Test: `tests/Feature/Domain/LiveMeeting/Jobs/LiveExtractionJobTest.php`

**Step 1: Create LiveTranscriptionJob**

Pattern from `ProcessTranscriptionJob`. The job:
1. Receives a `LiveTranscriptChunk` record
2. Updates status to Processing
3. Calls `TranscriberInterface->transcribe()` on the chunk's audio file
4. Updates chunk with transcribed text, speaker, confidence
5. Updates status to Completed
6. Broadcasts `TranscriptionChunkProcessed` event
7. On failure, updates status to Failed with error message

```php
<?php

namespace App\Domain\LiveMeeting\Jobs;

use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Events\TranscriptionChunkProcessed;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class LiveTranscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public LiveTranscriptChunk $chunk,
    ) {
        $this->onQueue('live-transcription');
    }

    public function handle(TranscriberInterface $transcriber): void
    {
        $this->chunk->update(['status' => ChunkStatus::Processing]);

        $filePath = Storage::disk('local')->path($this->chunk->audio_file_path);
        $result = $transcriber->transcribe($filePath, [
            'language' => $this->chunk->session?->meeting?->language ?? 'en',
        ]);

        $this->chunk->update([
            'text' => $result->fullText,
            'speaker' => $result->segments[0]->speaker ?? null,
            'confidence' => $result->confidence,
            'status' => ChunkStatus::Completed,
        ]);

        event(new TranscriptionChunkProcessed($this->chunk));
    }

    public function failed(\Throwable $exception): void
    {
        $this->chunk->update([
            'status' => ChunkStatus::Failed,
            'error_message' => $exception->getMessage(),
        ]);
    }
}
```

**Step 2: Create LiveExtractionJob**

This job aggregates all completed chunks and runs AI extraction:

```php
<?php

namespace App\Domain\LiveMeeting\Jobs;

use App\Domain\AI\Services\ExtractionService;
use App\Domain\LiveMeeting\Events\LiveExtractionUpdated;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LiveExtractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public LiveMeetingSession $session,
    ) {
        $this->onQueue('live-extraction');
    }

    public function handle(ExtractionService $extractionService): void
    {
        $transcriptText = $this->session->getCompletedTranscriptText();

        if (empty(trim($transcriptText))) {
            return;
        }

        $meeting = $this->session->meeting;

        // Temporarily set meeting content to transcript text for extraction
        $originalContent = $meeting->content;
        $meeting->content = $transcriptText;

        $extractionService->extractAll($meeting);

        // Restore original content
        $meeting->content = $originalContent;
        $meeting->save();

        $extractions = $meeting->extractions()
            ->get()
            ->mapWithKeys(fn ($e) => [$e->type => $e->structured_data ?? $e->content]);

        event(new LiveExtractionUpdated($this->session, $extractions->all()));
    }
}
```

**Step 3: Write tests, run, commit**

```bash
git add app/Domain/LiveMeeting/Jobs/ tests/Feature/Domain/LiveMeeting/Jobs/
git commit -m "feat: add LiveTranscriptionJob and LiveExtractionJob"
```

---

### Task 14: LiveMeetingController + Routes

**Files:**
- Create: `app/Domain/LiveMeeting/Controllers/LiveMeetingController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/LiveMeeting/Controllers/LiveMeetingControllerTest.php`

**Step 1: Create controller**

Endpoints:
- `POST meetings/{meeting}/live/start` — start live session
- `POST meetings/{meeting}/live/{session}/chunk` — upload audio chunk
- `POST meetings/{meeting}/live/{session}/end` — end session
- `GET meetings/{meeting}/live/{session}` — show live dashboard
- `GET meetings/{meeting}/live/{session}/state` — JSON current state (for polling fallback)

**Step 2: Add routes to `routes/web.php`**

Inside the `meetings/{meeting}` prefix group:

```php
Route::post('live/start', [\App\Domain\LiveMeeting\Controllers\LiveMeetingController::class, 'start'])->name('live.start');
Route::get('live/{session}', [\App\Domain\LiveMeeting\Controllers\LiveMeetingController::class, 'show'])->name('live.show');
Route::post('live/{session}/chunk', [\App\Domain\LiveMeeting\Controllers\LiveMeetingController::class, 'chunk'])->name('live.chunk');
Route::post('live/{session}/end', [\App\Domain\LiveMeeting\Controllers\LiveMeetingController::class, 'end'])->name('live.end');
Route::get('live/{session}/state', [\App\Domain\LiveMeeting\Controllers\LiveMeetingController::class, 'state'])->name('live.state');
```

**Step 3: Write tests, run, commit**

```bash
git add app/Domain/LiveMeeting/Controllers/ routes/web.php tests/Feature/Domain/LiveMeeting/Controllers/
git commit -m "feat: add LiveMeetingController with start, chunk, end, state endpoints"
```

---

### Task 15: Live Dashboard Blade View

**Files:**
- Create: `resources/views/meetings/live-dashboard.blade.php`

**Step 1: Build the three-panel layout**

Left panel: Live transcript feed (auto-scrolling, speaker-labeled)
Center panel: AI extractions (decisions, action items, topics — auto-updating)
Right panel: Meeting controls (timer, attendance, recording controls)

Use Alpine.js with Echo for real-time updates. Reference `resources/js/meeting-live.js` for Echo subscription patterns.

**Step 2: Commit**

```bash
git add resources/views/meetings/live-dashboard.blade.php
git commit -m "feat: add live meeting dashboard three-panel Blade view"
```

---

### Task 16: Live Dashboard JavaScript (Alpine + Echo)

**Files:**
- Create: `resources/js/live-meeting-dashboard.js`
- Modify: `resources/js/audio-recorder.js` — add live mode (immediate 30s chunks)

**Step 1: Create the Alpine component**

The JS component needs to:
1. Subscribe to Echo private channel `live-meeting.{sessionId}`
2. Listen for `TranscriptionChunkProcessed` → append to transcript feed
3. Listen for `LiveExtractionUpdated` → update extractions panel
4. Listen for `LiveSessionEnded` → show "session ended" state
5. Control audio recording in "live mode" (30s chunks from start, not after 5 min)
6. Upload chunks via fetch to `/live/{session}/chunk` endpoint
7. Manage agenda timer and attendance controls

**Step 2: Modify audio-recorder.js**

Add a `liveMode` option that switches to immediate 30-second chunking from the start (instead of waiting 5 minutes). When `liveMode: true`:
- Set `chunkIntervalSeconds = 30` immediately
- Start chunking from first recording
- Use the live session chunk endpoint instead of the normal chunk endpoint

**Step 3: Run `npm run build` to compile**

**Step 4: Commit**

```bash
git add resources/js/live-meeting-dashboard.js resources/js/audio-recorder.js
git commit -m "feat: add live dashboard Alpine.js component with Echo integration"
```

---

### Task 17: Run Pint + Full Test Suite for Part B

**Step 1: Run Pint**

Run: `vendor/bin/pint --dirty --format agent`

**Step 2: Run full test suite**

Run: `php artisan test --compact`
Expected: All tests pass

**Step 3: Run `npm run build`**

Run: `npm run build`
Expected: Build succeeds

**Step 4: Commit**

```bash
git add -A
git commit -m "style: apply Pint formatting and build assets for live dashboard feature"
```

---

### Task 18: Integration Testing — End-to-End Flows

**Files:**
- Create: `tests/Feature/Domain/AI/PrepBriefIntegrationTest.php`
- Create: `tests/Feature/Domain/LiveMeeting/LiveMeetingIntegrationTest.php`

**Step 1: Write Prep Brief integration test**

Test the full flow: create meeting with attendees → run GeneratePrepBriefsJob → verify briefs created → verify notification sent → access via controller → verify viewed_at marked.

**Step 2: Write Live Meeting integration test**

Test: start session → upload chunk → verify transcription job dispatched → process chunk → verify broadcast event → end session → verify chunks merged into AudioTranscription.

**Step 3: Run all tests**

Run: `php artisan test --compact`
Expected: All PASS

**Step 4: Commit**

```bash
git add tests/Feature/Domain/AI/PrepBriefIntegrationTest.php tests/Feature/Domain/LiveMeeting/LiveMeetingIntegrationTest.php
git commit -m "test: add integration tests for prep brief and live meeting flows"
```

---

## Summary of All Files Created/Modified

### New Files (Part A — Prep Brief):
1. `database/migrations/..._create_meeting_prep_briefs_table.php`
2. `app/Domain/AI/Models/MeetingPrepBrief.php`
3. `database/factories/.../MeetingPrepBriefFactory.php`
4. `app/Domain/AI/Services/MeetingPrepBriefService.php`
5. `app/Domain/AI/Jobs/GeneratePrepBriefsJob.php`
6. `app/Domain/AI/Notifications/MeetingPrepBriefNotification.php`
7. `app/Domain/AI/Controllers/PrepBriefController.php`
8. `resources/views/meetings/prep-brief.blade.php`

### New Files (Part B — Live Dashboard):
9. `database/migrations/..._create_live_meeting_sessions_table.php`
10. `database/migrations/..._create_live_transcript_chunks_table.php`
11. `app/Domain/LiveMeeting/Enums/LiveSessionStatus.php`
12. `app/Domain/LiveMeeting/Enums/ChunkStatus.php`
13. `app/Domain/LiveMeeting/Models/LiveMeetingSession.php`
14. `app/Domain/LiveMeeting/Models/LiveTranscriptChunk.php`
15. `database/factories/.../LiveMeetingSessionFactory.php`
16. `database/factories/.../LiveTranscriptChunkFactory.php`
17. `app/Domain/LiveMeeting/Services/LiveMeetingService.php`
18. `app/Domain/LiveMeeting/Events/TranscriptionChunkProcessed.php`
19. `app/Domain/LiveMeeting/Events/LiveExtractionUpdated.php`
20. `app/Domain/LiveMeeting/Events/LiveSessionStarted.php`
21. `app/Domain/LiveMeeting/Events/LiveSessionEnded.php`
22. `app/Domain/LiveMeeting/Jobs/LiveTranscriptionJob.php`
23. `app/Domain/LiveMeeting/Jobs/LiveExtractionJob.php`
24. `app/Domain/LiveMeeting/Controllers/LiveMeetingController.php`
25. `resources/views/meetings/live-dashboard.blade.php`
26. `resources/js/live-meeting-dashboard.js`

### Modified Files:
27. `app/Domain/Meeting/Models/MinutesOfMeeting.php` — add `prepBriefs()` relationship
28. `routes/web.php` — add prep-brief and live-meeting routes
29. `routes/console.php` — add GeneratePrepBriefsJob schedule
30. `routes/channels.php` — add live-meeting broadcast channel
31. `resources/js/audio-recorder.js` — add live mode

### Test Files:
32. `tests/Feature/Domain/AI/Models/MeetingPrepBriefTest.php`
33. `tests/Feature/Domain/AI/Services/MeetingPrepBriefServiceTest.php`
34. `tests/Feature/Domain/AI/Jobs/GeneratePrepBriefsJobTest.php`
35. `tests/Feature/Domain/AI/Notifications/MeetingPrepBriefNotificationTest.php`
36. `tests/Feature/Domain/AI/Controllers/PrepBriefControllerTest.php`
37. `tests/Feature/Domain/LiveMeeting/Models/LiveMeetingSessionTest.php`
38. `tests/Feature/Domain/LiveMeeting/Services/LiveMeetingServiceTest.php`
39. `tests/Feature/Domain/LiveMeeting/Jobs/LiveTranscriptionJobTest.php`
40. `tests/Feature/Domain/LiveMeeting/Jobs/LiveExtractionJobTest.php`
41. `tests/Feature/Domain/LiveMeeting/Controllers/LiveMeetingControllerTest.php`
42. `tests/Feature/Domain/AI/PrepBriefIntegrationTest.php`
43. `tests/Feature/Domain/LiveMeeting/LiveMeetingIntegrationTest.php`
