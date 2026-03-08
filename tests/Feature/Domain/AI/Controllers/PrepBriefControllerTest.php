<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);

    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->attendee = MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'user_id' => $this->user->id,
    ]);

    config(['ai.default' => 'openai']);
    config(['ai.providers.openai.api_key' => 'test-key']);
    config(['ai.providers.openai.model' => 'gpt-4o']);
});

test('shows prep brief page for authenticated user', function () {
    $brief = MeetingPrepBrief::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'attendee_id' => $this->attendee->id,
        'user_id' => $this->user->id,
        'content' => [
            'executive_summary' => 'This is the executive summary for the upcoming meeting.',
            'action_items' => ['overdue' => [], 'pending' => [], 'completed' => []],
            'unresolved_items' => [],
            'agenda_deep_dive' => [],
            'metrics' => ['attendance_rate' => 0.95, 'total_meetings_6m' => 12, 'action_completion_rate' => 0.8],
            'reading_list' => [],
            'conflicts' => [],
        ],
        'summary_highlights' => ['Highlight one', 'Highlight two'],
        'estimated_prep_minutes' => 15,
        'generated_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.prep-brief', $this->meeting));

    $response->assertOk();
    $response->assertSee('Prep Brief');
    $response->assertSee('This is the executive summary for the upcoming meeting.');
});

test('marks brief as viewed when accessed', function () {
    $brief = MeetingPrepBrief::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'attendee_id' => $this->attendee->id,
        'user_id' => $this->user->id,
        'viewed_at' => null,
        'generated_at' => now(),
    ]);

    $this->assertNull($brief->viewed_at);

    $this->actingAs($this->user)
        ->get(route('meetings.prep-brief', $this->meeting));

    $brief->refresh();
    $this->assertNotNull($brief->viewed_at);
});

test('can manually trigger brief generation', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'executive_summary' => 'AI-generated summary.',
                'suggested_questions' => ['Question 1'],
                'reading_priorities' => ['Priority 1'],
            ])]]],
        ]),
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('meetings.prep-brief.generate', $this->meeting));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->assertDatabaseHas('meeting_prep_briefs', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'user_id' => $this->user->id,
    ]);
});

test('tracks section reads via JSON endpoint', function () {
    $brief = MeetingPrepBrief::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'attendee_id' => $this->attendee->id,
        'user_id' => $this->user->id,
        'sections_read' => null,
        'generated_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.prep-brief.section-read', [$this->meeting, $brief]), [
            'section' => 'executive_summary',
        ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    $brief->refresh();
    $this->assertContains('executive_summary', $brief->sections_read);
});

test('returns 200 even when no brief exists', function () {
    $response = $this->actingAs($this->user)
        ->get(route('meetings.prep-brief', $this->meeting));

    $response->assertOk();
    $response->assertSee('Generate Prep Brief');
});

test('section read validation rejects missing section', function () {
    $brief = MeetingPrepBrief::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'attendee_id' => $this->attendee->id,
        'user_id' => $this->user->id,
        'generated_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.prep-brief.section-read', [$this->meeting, $brief]), []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('section');
});

test('unauthenticated user cannot access prep brief', function () {
    $response = $this->get(route('meetings.prep-brief', $this->meeting));

    $response->assertRedirect(route('login'));
});
