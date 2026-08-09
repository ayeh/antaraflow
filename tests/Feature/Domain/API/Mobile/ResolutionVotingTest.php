<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MeetingResolution;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\ResolutionVote;
use App\Models\User;
use App\Support\Enums\ResolutionStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->meeting = MinutesOfMeeting::createForOrganization($this->org->id, [
        'title' => 'Board meeting',
        'created_by' => $this->user->id,
    ]);

    $this->attendee = $this->meeting->attendees()->create([
        'user_id' => $this->user->id,
        'name' => $this->user->name,
        'email' => $this->user->email,
    ]);

    $this->resolution = MeetingResolution::query()->create([
        'meeting_id' => $this->meeting->id,
        'resolution_number' => 'R2026/001',
        'title' => 'Approve the Q4 capital budget',
        'status' => ResolutionStatus::Proposed,
    ]);
});

test('a vote is recorded and reflected in the tally', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/resolutions/{$this->resolution->id}/vote", ['vote' => 'for']);

    $response->assertOk()
        ->assertJsonPath('my_vote', 'for')
        ->assertJsonPath('tally.for', 1)
        ->assertJsonPath('tally.against', 0);

    expect(ResolutionVote::query()->count())->toBe(1);
});

test('changing a vote replaces it rather than adding a second', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/resolutions/{$this->resolution->id}/vote", ['vote' => 'for'])
        ->assertOk();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/resolutions/{$this->resolution->id}/vote", ['vote' => 'against'])
        ->assertOk()
        ->assertJsonPath('my_vote', 'against')
        ->assertJsonPath('tally.for', 0)
        ->assertJsonPath('tally.against', 1);

    expect(ResolutionVote::query()->count())->toBe(1);
});

test('voting is refused once the resolution is no longer proposed', function () {
    $this->resolution->update(['status' => ResolutionStatus::Passed]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/resolutions/{$this->resolution->id}/vote", ['vote' => 'for'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'VOTING_CLOSED');

    expect(ResolutionVote::query()->count())->toBe(0);
});

test('someone who is not an attendee cannot vote', function () {
    $colleague = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($colleague, ['role' => UserRole::Manager->value]);

    $this->actingAs($colleague, 'sanctum')
        ->postJson("/api/mobile/v1/resolutions/{$this->resolution->id}/vote", ['vote' => 'for'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'NOT_AN_ATTENDEE');
});

test('a resolution in another organization is not reachable', function () {
    $otherOrg = Organization::factory()->create();
    $outsider = User::factory()->create(['current_organization_id' => $otherOrg->id]);
    $otherOrg->members()->attach($outsider, ['role' => UserRole::Owner->value]);

    $this->actingAs($outsider, 'sanctum')
        ->postJson("/api/mobile/v1/resolutions/{$this->resolution->id}/vote", ['vote' => 'for'])
        ->assertStatus(403);

    expect(ResolutionVote::query()->count())->toBe(0);
});

test('closing a resolution records the outcome the tally produces', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/resolutions/{$this->resolution->id}/vote", ['vote' => 'for'])
        ->assertOk();

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/resolutions/{$this->resolution->id}/close")
        ->assertOk()
        ->assertJsonPath('status', 'passed')
        ->assertJsonPath('voting_open', false);
});

test('the resolution list reports the viewers own vote', function () {
    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/resolutions/{$this->resolution->id}/vote", ['vote' => 'abstain'])
        ->assertOk();

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/mobile/v1/meetings/{$this->meeting->id}/resolutions")
        ->assertOk()
        ->assertJsonPath('data.0.my_vote', 'abstain')
        ->assertJsonPath('data.0.tally.total_eligible', 1)
        ->assertJsonPath('data.0.tally.not_voted', 0);
});
