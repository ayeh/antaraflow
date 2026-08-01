<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);

    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
    $this->session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
        'config' => ['chunk_interval' => 30, 'extraction_interval' => 300, 'live_extraction' => false],
    ]);
});

it('turns live extraction on mid-session', function () {
    $this->actingAs($this->user)
        ->postJson(route('meetings.live.extraction', [$this->meeting, $this->session]), ['enabled' => true])
        ->assertOk()
        ->assertJson(['live_extraction' => true]);

    expect($this->session->fresh()->config['live_extraction'])->toBeTrue();
});

it('turns it off again without ending the session', function () {
    $this->session->update(['config' => [...$this->session->config, 'live_extraction' => true]]);

    $this->actingAs($this->user)
        ->postJson(route('meetings.live.extraction', [$this->meeting, $this->session]), ['enabled' => false])
        ->assertOk();

    expect($this->session->fresh()->config['live_extraction'])->toBeFalse()
        ->and($this->session->fresh()->status)->toBe(LiveSessionStatus::Active);
});

it('leaves the rest of the session config alone', function () {
    $this->actingAs($this->user)
        ->postJson(route('meetings.live.extraction', [$this->meeting, $this->session]), ['enabled' => true])
        ->assertOk();

    expect($this->session->fresh()->config)
        ->toBe(['chunk_interval' => 30, 'extraction_interval' => 300, 'live_extraction' => true]);
});

it('rejects a toggle once the session has ended', function () {
    $this->session->update(['status' => LiveSessionStatus::Ended]);

    $this->actingAs($this->user)
        ->postJson(route('meetings.live.extraction', [$this->meeting, $this->session]), ['enabled' => true])
        ->assertStatus(409);
});

it('hides the session from another organisation entirely', function () {
    $otherOrg = Organization::factory()->create();
    $outsider = User::factory()->create(['current_organization_id' => $otherOrg->id]);
    $otherOrg->members()->attach($outsider, ['role' => UserRole::Manager->value]);

    $this->actingAs($outsider)
        ->postJson(route('meetings.live.extraction', [$this->meeting, $this->session]), ['enabled' => true])
        // The tenant scope removes the meeting before the policy is consulted,
        // so an outsider is told it does not exist rather than that it is barred.
        ->assertNotFound();
});

it('requires an explicit on or off', function () {
    $this->actingAs($this->user)
        ->postJson(route('meetings.live.extraction', [$this->meeting, $this->session]), [])
        ->assertStatus(422);
});
