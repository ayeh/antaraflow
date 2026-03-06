<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\BoardSetting;
use App\Domain\Meeting\Models\MeetingResolution;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Services\ResolutionService;
use App\Models\User;
use App\Support\Enums\MeetingType;
use App\Support\Enums\ResolutionStatus;
use App\Support\Enums\UserRole;
use App\Support\Enums\VoteChoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_type' => MeetingType::BoardMeeting,
    ]);

    BoardSetting::factory()->create([
        'organization_id' => $this->org->id,
        'voting_enabled' => true,
    ]);
});

it('only allows present attendees to vote via controller', function () {
    $presentAttendee = MomAttendee::factory()->present()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
    ]);

    $resolution = MeetingResolution::factory()->create([
        'meeting_id' => $this->meeting->id,
    ]);

    $response = $this->actingAs($this->user)->post(
        route('meetings.resolutions.vote', [$this->meeting, $resolution]),
        [
            'attendee_id' => $presentAttendee->id,
            'vote' => VoteChoice::For->value,
        ]
    );

    $response->assertRedirect();
    expect($resolution->votes()->count())->toBe(1);
});

it('calculates vote tally correctly', function () {
    $resolution = MeetingResolution::factory()->create([
        'meeting_id' => $this->meeting->id,
    ]);

    $attendees = MomAttendee::factory()->count(5)->present()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
    ]);

    $resolutionService = app(ResolutionService::class);
    $resolutionService->castVote($resolution, $attendees[0]->id, VoteChoice::For);
    $resolutionService->castVote($resolution, $attendees[1]->id, VoteChoice::For);
    $resolutionService->castVote($resolution, $attendees[2]->id, VoteChoice::For);
    $resolutionService->castVote($resolution, $attendees[3]->id, VoteChoice::Against);
    $resolutionService->castVote($resolution, $attendees[4]->id, VoteChoice::Abstain);

    $resolution->loadMissing('votes');

    $forCount = $resolution->votes->where('vote', VoteChoice::For)->count();
    $againstCount = $resolution->votes->where('vote', VoteChoice::Against)->count();
    $abstainCount = $resolution->votes->where('vote', VoteChoice::Abstain)->count();

    expect($forCount)->toBe(3);
    expect($againstCount)->toBe(1);
    expect($abstainCount)->toBe(1);
    expect($resolution->votes->count())->toBe(5);
});

it('updates resolution status after calculating result', function () {
    $resolution = MeetingResolution::factory()->create([
        'meeting_id' => $this->meeting->id,
    ]);

    $attendees = MomAttendee::factory()->count(3)->present()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
    ]);

    $resolutionService = app(ResolutionService::class);
    $resolutionService->castVote($resolution, $attendees[0]->id, VoteChoice::For);
    $resolutionService->castVote($resolution, $attendees[1]->id, VoteChoice::Against);
    $resolutionService->castVote($resolution, $attendees[2]->id, VoteChoice::Against);

    $result = $resolutionService->calculateResult($resolution);
    expect($result)->toBe(ResolutionStatus::Failed);
});

it('withdraws a resolution and changes its status', function () {
    $resolution = MeetingResolution::factory()->create([
        'meeting_id' => $this->meeting->id,
    ]);

    $resolutionService = app(ResolutionService::class);
    $resolutionService->update($resolution, ['status' => ResolutionStatus::Withdrawn->value]);

    expect($resolution->fresh()->status)->toBe(ResolutionStatus::Withdrawn);
});

it('shows board compliance partial only for board meetings', function () {
    // Board meeting should show compliance section
    $boardMeeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_type' => MeetingType::BoardMeeting,
    ]);

    $response = $this->actingAs($this->user)->get(route('meetings.show', ['meeting' => $boardMeeting, 'step' => 5]));
    $response->assertSuccessful();
    $response->assertSee('Board Compliance');

    // General meeting should NOT show compliance section
    $generalMeeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_type' => MeetingType::General,
    ]);

    $response = $this->actingAs($this->user)->get(route('meetings.show', ['meeting' => $generalMeeting, 'step' => 5]));
    $response->assertSuccessful();
    $response->assertDontSee('Board Compliance');
});
