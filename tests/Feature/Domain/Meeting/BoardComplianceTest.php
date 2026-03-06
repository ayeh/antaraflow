<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\BoardSetting;
use App\Domain\Meeting\Models\MeetingResolution;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Services\QuorumService;
use App\Domain\Meeting\Services\ResolutionService;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
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
});

it('loads the board settings page', function () {
    $response = $this->actingAs($this->user)->get(route('settings.board.edit'));

    $response->assertSuccessful();
    $response->assertSee('Board Meeting Settings');
});

it('updates board settings correctly', function () {
    $response = $this->actingAs($this->user)->put(route('settings.board.update'), [
        'quorum_type' => 'count',
        'quorum_value' => 5,
        'require_chair' => true,
        'require_secretary' => true,
        'voting_enabled' => true,
        'chair_casting_vote' => true,
        'block_finalization_without_quorum' => true,
    ]);

    $response->assertRedirect(route('settings.board.edit'));

    $boardSetting = BoardSetting::where('organization_id', $this->org->id)->first();
    expect($boardSetting)
        ->quorum_type->toBe('count')
        ->quorum_value->toBe(5)
        ->require_chair->toBeTrue()
        ->require_secretary->toBeTrue()
        ->voting_enabled->toBeTrue()
        ->chair_casting_vote->toBeTrue()
        ->block_finalization_without_quorum->toBeTrue();
});

it('checks quorum with percentage type', function () {
    BoardSetting::factory()->create([
        'organization_id' => $this->org->id,
        'quorum_type' => 'percentage',
        'quorum_value' => 50,
    ]);

    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_type' => MeetingType::BoardMeeting,
    ]);

    // 4 attendees, 2 present = 50% = exactly quorum
    MomAttendee::factory()->count(2)->present()->create(['minutes_of_meeting_id' => $meeting->id]);
    MomAttendee::factory()->count(2)->create(['minutes_of_meeting_id' => $meeting->id, 'is_present' => false]);

    $quorumService = app(QuorumService::class);
    $result = $quorumService->check($meeting);

    expect($result['is_met'])->toBeTrue();
    expect($result['required'])->toBe(2);
    expect($result['present'])->toBe(2);
    expect($result['type'])->toBe('percentage');
});

it('checks quorum with count type', function () {
    BoardSetting::factory()->countBased(3)->create([
        'organization_id' => $this->org->id,
    ]);

    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_type' => MeetingType::BoardMeeting,
    ]);

    // Only 2 present, but need 3
    MomAttendee::factory()->count(2)->present()->create(['minutes_of_meeting_id' => $meeting->id]);
    MomAttendee::factory()->count(3)->create(['minutes_of_meeting_id' => $meeting->id, 'is_present' => false]);

    $quorumService = app(QuorumService::class);
    $result = $quorumService->check($meeting);

    expect($result['is_met'])->toBeFalse();
    expect($result['required'])->toBe(3);
    expect($result['present'])->toBe(2);
});

it('auto-generates resolution number when creating a resolution', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_type' => MeetingType::BoardMeeting,
    ]);

    $resolutionService = app(ResolutionService::class);
    $resolution = $resolutionService->create($meeting, [
        'title' => 'Approve Budget',
        'description' => 'Approve the annual budget for 2026.',
    ]);

    expect($resolution->resolution_number)->toStartWith('RES-'.now()->format('Y').'-');
    expect($resolution->status)->toBe(ResolutionStatus::Proposed);
});

it('records a vote correctly', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_type' => MeetingType::BoardMeeting,
    ]);

    $attendee = MomAttendee::factory()->present()->create(['minutes_of_meeting_id' => $meeting->id]);

    $resolution = MeetingResolution::factory()->create(['meeting_id' => $meeting->id]);

    $resolutionService = app(ResolutionService::class);
    $vote = $resolutionService->castVote($resolution, $attendee->id, VoteChoice::For);

    expect($vote->vote)->toBe(VoteChoice::For);
    expect($vote->attendee_id)->toBe($attendee->id);
    expect($vote->resolution_id)->toBe($resolution->id);
});

it('cannot vote twice on same resolution (updates instead)', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_type' => MeetingType::BoardMeeting,
    ]);

    $attendee = MomAttendee::factory()->present()->create(['minutes_of_meeting_id' => $meeting->id]);

    $resolution = MeetingResolution::factory()->create(['meeting_id' => $meeting->id]);

    $resolutionService = app(ResolutionService::class);
    $resolutionService->castVote($resolution, $attendee->id, VoteChoice::For);
    $resolutionService->castVote($resolution, $attendee->id, VoteChoice::Against);

    expect($resolution->votes()->count())->toBe(1);
    expect($resolution->votes()->first()->vote)->toBe(VoteChoice::Against);
});

it('passes a resolution with majority for votes', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_type' => MeetingType::BoardMeeting,
    ]);

    $resolution = MeetingResolution::factory()->create(['meeting_id' => $meeting->id]);

    $attendees = MomAttendee::factory()->count(3)->present()->create(['minutes_of_meeting_id' => $meeting->id]);

    $resolutionService = app(ResolutionService::class);
    $resolutionService->castVote($resolution, $attendees[0]->id, VoteChoice::For);
    $resolutionService->castVote($resolution, $attendees[1]->id, VoteChoice::For);
    $resolutionService->castVote($resolution, $attendees[2]->id, VoteChoice::Against);

    $result = $resolutionService->calculateResult($resolution);
    expect($result)->toBe(ResolutionStatus::Passed);
});

it('uses chair casting vote to break a tie', function () {
    BoardSetting::factory()->withChairCastingVote()->create([
        'organization_id' => $this->org->id,
    ]);

    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_type' => MeetingType::BoardMeeting,
    ]);

    $resolution = MeetingResolution::factory()->create(['meeting_id' => $meeting->id]);

    $attendees = MomAttendee::factory()->count(2)->present()->create(['minutes_of_meeting_id' => $meeting->id]);

    $resolutionService = app(ResolutionService::class);
    $resolutionService->castVote($resolution, $attendees[0]->id, VoteChoice::For);
    $resolutionService->castVote($resolution, $attendees[1]->id, VoteChoice::Against);

    $result = $resolutionService->calculateResult($resolution);
    expect($result)->toBe(ResolutionStatus::Passed);
});

it('blocks finalization without quorum when configured', function () {
    BoardSetting::factory()->withQuorumBlocking()->create([
        'organization_id' => $this->org->id,
        'quorum_type' => 'count',
        'quorum_value' => 3,
    ]);

    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_type' => MeetingType::BoardMeeting,
    ]);

    // Only 1 attendee present, need 3
    MomAttendee::factory()->present()->create(['minutes_of_meeting_id' => $meeting->id]);
    MomAttendee::factory()->count(2)->create(['minutes_of_meeting_id' => $meeting->id, 'is_present' => false]);

    $response = $this->actingAs($this->user)->post(route('meetings.finalize', $meeting));

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect($meeting->fresh()->status)->toBe(MeetingStatus::Draft);
});
