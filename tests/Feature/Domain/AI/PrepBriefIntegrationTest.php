<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Jobs\GeneratePrepBriefsJob;
use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\AI\Notifications\MeetingPrepBriefNotification;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);

    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->addHours(12),
        'status' => MeetingStatus::Draft,
    ]);

    $this->attendee = MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'user_id' => $this->user->id,
    ]);

    // Mock the AI provider so service calls don't hit a real API.
    $fakeProvider = Mockery::mock(AIProviderInterface::class);
    $fakeProvider->shouldReceive('chat')->andReturn(json_encode([
        'executive_summary' => 'AI-generated executive summary for integration test.',
        'suggested_questions' => ['What is the timeline?', 'Who is responsible?'],
        'reading_priorities' => ['Review last meeting notes'],
    ]));

    $this->app->instance(AIProviderInterface::class, $fakeProvider);

    config(['ai.default' => 'openai']);
    config(['ai.providers.openai.api_key' => 'test-key']);
    config(['ai.providers.openai.model' => 'gpt-4o']);
});

test('full prep brief flow: generate via job, notify, view, and track sections', function () {
    Notification::fake();

    // Step 1: Create a second attendee to verify briefs are generated for each.
    $secondUser = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($secondUser, ['role' => UserRole::Manager->value]);
    $secondAttendee = MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'user_id' => $secondUser->id,
    ]);

    // Step 2: Mock AI provider at the factory level since the service resolves its own provider.
    $fakeProvider = Mockery::mock(AIProviderInterface::class);
    $fakeProvider->shouldReceive('chat')->andReturn(json_encode([
        'executive_summary' => 'AI-generated executive summary for integration test.',
        'suggested_questions' => ['What is the timeline?'],
        'reading_priorities' => ['Review last meeting notes'],
    ]));

    $this->mock(\App\Infrastructure\AI\AIProviderFactory::class, function ($mock) use ($fakeProvider) {
        $mock->shouldReceive('make')->andReturn($fakeProvider);
    });

    // Step 3: Run the job synchronously.
    (new GeneratePrepBriefsJob)->handle();

    // Step 4: Verify briefs were created for both attendees.
    $this->assertDatabaseHas('meeting_prep_briefs', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'attendee_id' => $this->attendee->id,
        'user_id' => $this->user->id,
    ]);
    $this->assertDatabaseHas('meeting_prep_briefs', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'attendee_id' => $secondAttendee->id,
        'user_id' => $secondUser->id,
    ]);

    // Step 5: Verify notifications were sent.
    Notification::assertSentTo($this->user, MeetingPrepBriefNotification::class);
    Notification::assertSentTo($secondUser, MeetingPrepBriefNotification::class);

    // Step 6: Access brief via controller route and verify viewed_at is set.
    $brief = MeetingPrepBrief::query()
        ->where('minutes_of_meeting_id', $this->meeting->id)
        ->where('user_id', $this->user->id)
        ->first();

    expect($brief)->not->toBeNull();
    expect($brief->viewed_at)->toBeNull();

    $response = $this->actingAs($this->user)
        ->get(route('meetings.prep-brief', $this->meeting));

    $response->assertOk();

    $brief->refresh();
    expect($brief->viewed_at)->not->toBeNull();

    // Step 7: Track section reads.
    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.prep-brief.section-read', [$this->meeting, $brief]), [
            'section' => 'executive_summary',
        ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);

    $brief->refresh();
    expect($brief->sections_read)->toContain('executive_summary');

    // Mark another section as read.
    $this->actingAs($this->user)
        ->postJson(route('meetings.prep-brief.section-read', [$this->meeting, $brief]), [
            'section' => 'action_items',
        ])
        ->assertOk();

    $brief->refresh();
    expect($brief->sections_read)->toContain('executive_summary');
    expect($brief->sections_read)->toContain('action_items');
    expect($brief->sections_read)->toHaveCount(2);
});

test('generate prep brief via controller endpoint triggers service and creates brief', function () {
    // Mock AI at HTTP level, matching the existing controller test pattern.
    \Illuminate\Support\Facades\Http::fake([
        'api.openai.com/*' => \Illuminate\Support\Facades\Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'executive_summary' => 'Generated summary via controller.',
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

    $brief = MeetingPrepBrief::query()
        ->where('minutes_of_meeting_id', $this->meeting->id)
        ->where('user_id', $this->user->id)
        ->first();

    expect($brief->content)->toBeArray();
    expect($brief->content['executive_summary'])->toBeString();
    expect($brief->estimated_prep_minutes)->toBeGreaterThanOrEqual(5);
    expect($brief->generated_at)->not->toBeNull();
});

test('job skips meetings outside the 24-hour window', function () {
    // Move meeting far into the future so it's outside the 24-hour window.
    $this->meeting->update(['meeting_date' => now()->addWeek()]);

    (new GeneratePrepBriefsJob)->handle();

    $this->assertDatabaseCount('meeting_prep_briefs', 0);
});

test('job skips meetings without attendees', function () {
    // Remove the attendee.
    $this->attendee->delete();

    (new GeneratePrepBriefsJob)->handle();

    $this->assertDatabaseCount('meeting_prep_briefs', 0);
});

test('viewing brief marks viewed_at and subsequent views do not reset it', function () {
    $brief = MeetingPrepBrief::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'attendee_id' => $this->attendee->id,
        'user_id' => $this->user->id,
        'viewed_at' => null,
        'generated_at' => now(),
    ]);

    // First view sets viewed_at.
    $this->actingAs($this->user)
        ->get(route('meetings.prep-brief', $this->meeting));

    $brief->refresh();
    $firstViewedAt = $brief->viewed_at;
    expect($firstViewedAt)->not->toBeNull();

    // Second view should not reset viewed_at (it's already set).
    $this->travel(5)->minutes();
    $this->actingAs($this->user)
        ->get(route('meetings.prep-brief', $this->meeting));

    $brief->refresh();
    expect($brief->viewed_at->timestamp)->toBe($firstViewedAt->timestamp);
});
