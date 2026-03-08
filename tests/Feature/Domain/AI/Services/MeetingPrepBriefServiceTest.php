<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\AI\Services\MeetingPrepBriefService;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Project\Models\Project;
use App\Models\User;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\MeetingStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->project = Project::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'project_id' => $this->project->id,
        'status' => MeetingStatus::Draft,
        'meeting_date' => now()->addDay(),
    ]);

    $this->attendee = MomAttendee::factory()->present()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'user_id' => $this->user->id,
    ]);

    config(['ai.default' => 'openai']);
    config(['ai.providers.openai.api_key' => 'test-key']);
    config(['ai.providers.openai.model' => 'gpt-4o']);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'executive_summary' => 'This meeting will cover project updates and action item reviews.',
                'suggested_questions' => ['What is the timeline for deliverables?', 'Are there any blockers?'],
                'reading_priorities' => ['Review the project charter first', 'Skim the budget report'],
            ])]]],
        ]),
    ]);

    $this->service = app(MeetingPrepBriefService::class);
});

test('generates a brief for a single attendee with expected content structure', function () {
    $brief = $this->service->generateForAttendee($this->meeting, $this->attendee);

    expect($brief)->toBeInstanceOf(MeetingPrepBrief::class)
        ->and($brief->exists)->toBeTrue()
        ->and($brief->minutes_of_meeting_id)->toBe($this->meeting->id)
        ->and($brief->attendee_id)->toBe($this->attendee->id)
        ->and($brief->user_id)->toBe($this->user->id)
        ->and($brief->content)->toBeArray()
        ->and($brief->content)->toHaveKeys([
            'executive_summary',
            'action_items',
            'unresolved_items',
            'agenda_deep_dive',
            'metrics',
            'reading_list',
            'conflicts',
        ])
        ->and($brief->summary_highlights)->toBeArray()
        ->and($brief->generated_at)->not->toBeNull()
        ->and($brief->estimated_prep_minutes)->toBeGreaterThanOrEqual(0);
});

test('includes overdue action items for the attendee', function () {
    ActionItem::factory()->overdue()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
        'title' => 'Overdue task for attendee',
        'status' => ActionItemStatus::Open,
        'due_date' => now()->subDays(5),
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
        'title' => 'Pending task for attendee',
        'status' => ActionItemStatus::Open,
        'due_date' => now()->addDays(7),
    ]);

    $brief = $this->service->generateForAttendee($this->meeting, $this->attendee);

    $actionItems = $brief->content['action_items'];
    expect($actionItems)->toHaveKeys(['overdue', 'pending', 'completed'])
        ->and($actionItems['overdue'])->toHaveCount(1)
        ->and($actionItems['overdue'][0]['title'])->toBe('Overdue task for attendee')
        ->and($actionItems['overdue'][0]['days_overdue'])->toBeGreaterThanOrEqual(5)
        ->and($actionItems['pending'])->toHaveCount(1)
        ->and($actionItems['pending'][0]['title'])->toBe('Pending task for attendee');
});

test('generates briefs for all attendees of a meeting', function () {
    $secondUser = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($secondUser, ['role' => UserRole::Member->value]);

    MomAttendee::factory()->present()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'user_id' => $secondUser->id,
    ]);

    $briefs = $this->service->generateForMeeting($this->meeting);

    expect($briefs)->toHaveCount(2);
    expect(MeetingPrepBrief::where('minutes_of_meeting_id', $this->meeting->id)->count())->toBe(2);
});

test('replaces existing brief when regenerated (idempotent)', function () {
    $firstBrief = $this->service->generateForAttendee($this->meeting, $this->attendee);
    $firstId = $firstBrief->id;

    expect(MeetingPrepBrief::where('minutes_of_meeting_id', $this->meeting->id)
        ->where('attendee_id', $this->attendee->id)
        ->count())->toBe(1);

    $secondBrief = $this->service->generateForAttendee($this->meeting, $this->attendee);

    expect($secondBrief->id)->not->toBe($firstId)
        ->and(MeetingPrepBrief::where('minutes_of_meeting_id', $this->meeting->id)
            ->where('attendee_id', $this->attendee->id)
            ->count())->toBe(1);
});

test('calculates estimated prep time', function () {
    $brief = $this->service->generateForAttendee($this->meeting, $this->attendee);

    expect($brief->estimated_prep_minutes)->toBeInt()
        ->and($brief->estimated_prep_minutes)->toBeGreaterThan(0);
});
