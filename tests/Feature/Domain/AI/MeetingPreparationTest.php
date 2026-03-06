<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Models\ExtractionTemplate;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Project\Models\Project;
use App\Models\User;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\ExtractionType;
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
    ]);

    config(['ai.default' => 'openai']);
    config(['ai.providers.openai.api_key' => 'test-key']);
    config(['ai.providers.openai.model' => 'gpt-4o']);
});

test('generate returns suggested agenda JSON', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'suggested_agenda' => ['Review previous action items', 'Discuss project timeline'],
                'carryover_items' => [['title' => 'Update docs', 'status' => 'open']],
                'discussion_topics' => ['Budget review', 'Team allocation'],
                'estimated_duration_minutes' => 45,
            ])]]],
        ]),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('meetings.prepare-agenda.generate', $this->meeting));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'suggested_agenda',
            'carryover_items',
            'discussion_topics',
            'estimated_duration_minutes',
        ])
        ->assertJsonPath('suggested_agenda.0', 'Review previous action items')
        ->assertJsonPath('estimated_duration_minutes', 45);
});

test('generate gathers context from past project meetings', function () {
    $previousMeeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'project_id' => $this->project->id,
        'title' => 'Previous Sprint Review',
        'status' => MeetingStatus::Approved,
        'meeting_date' => now()->subDays(7),
    ]);

    MomExtraction::query()->create([
        'minutes_of_meeting_id' => $previousMeeting->id,
        'type' => 'summary',
        'content' => 'Reviewed sprint progress and blockers.',
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ]);

    MomExtraction::query()->create([
        'minutes_of_meeting_id' => $previousMeeting->id,
        'type' => 'decisions',
        'content' => '- Decided to extend deadline by one week',
        'provider' => 'openai',
        'model' => 'gpt-4o',
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'suggested_agenda' => ['Follow up on extended deadline'],
                'carryover_items' => [],
                'discussion_topics' => ['Sprint progress'],
                'estimated_duration_minutes' => 30,
            ])]]],
        ]),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('meetings.prepare-agenda.generate', $this->meeting));

    $response->assertSuccessful();

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        $messages = $body['messages'] ?? [];
        foreach ($messages as $msg) {
            if (str_contains($msg['content'] ?? '', 'Previous Sprint Review')) {
                return true;
            }
        }

        return false;
    });
});

test('generate includes open action items for attendees', function () {
    $attendeeUser = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($attendeeUser, ['role' => UserRole::Member->value]);

    MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'user_id' => $attendeeUser->id,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'assigned_to' => $attendeeUser->id,
        'created_by' => $this->user->id,
        'title' => 'Complete API documentation',
        'status' => ActionItemStatus::Open,
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'suggested_agenda' => ['Review API documentation progress'],
                'carryover_items' => [],
                'discussion_topics' => [],
                'estimated_duration_minutes' => 30,
            ])]]],
        ]),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('meetings.prepare-agenda.generate', $this->meeting));

    $response->assertSuccessful();

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        $messages = $body['messages'] ?? [];
        foreach ($messages as $msg) {
            if (str_contains($msg['content'] ?? '', 'Complete API documentation')) {
                return true;
            }
        }

        return false;
    });
});

test('apply saves agenda to meeting metadata', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.prepare-agenda.apply', $this->meeting), [
            'agenda' => [
                'Review action items',
                'Discuss project timeline',
                'Plan next sprint',
            ],
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('message', 'Agenda applied successfully.');

    $this->meeting->refresh();

    expect($this->meeting->metadata)->toHaveKey('agenda')
        ->and($this->meeting->metadata['agenda'])->toHaveCount(3)
        ->and($this->meeting->metadata['agenda'][0])->toBe('Review action items');
});

test('cannot generate for approved meeting', function () {
    $approvedMeeting = MinutesOfMeeting::factory()->approved()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'project_id' => $this->project->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('meetings.prepare-agenda.generate', $approvedMeeting));

    $response->assertStatus(422)
        ->assertJsonPath('error', 'Meeting preparation is only available for draft or in-progress meetings.');
});

test('supports custom extraction template', function () {
    ExtractionTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'extraction_type' => ExtractionType::MeetingPreparation,
        'meeting_type' => null,
        'prompt_template' => 'Custom meeting prep prompt: {transcript}',
        'system_message' => 'You are a custom meeting expert.',
        'is_active' => true,
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'suggested_agenda' => ['Custom agenda item'],
                'carryover_items' => [],
                'discussion_topics' => [],
                'estimated_duration_minutes' => 60,
            ])]]],
        ]),
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('meetings.prepare-agenda.generate', $this->meeting));

    $response->assertSuccessful()
        ->assertJsonPath('suggested_agenda.0', 'Custom agenda item');

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        $messages = $body['messages'] ?? [];
        foreach ($messages as $msg) {
            if (str_contains($msg['content'] ?? '', 'Custom meeting prep prompt:')) {
                return true;
            }
        }

        return false;
    });
});
