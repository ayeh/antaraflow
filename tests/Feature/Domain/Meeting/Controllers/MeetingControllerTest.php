<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('user can list meetings', function () {
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Test Meeting',
        'meeting_date' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('meetings.index'));

    $response->assertSuccessful();
    $response->assertSee('Test Meeting');
});

test('user can create a meeting', function () {
    $response = $this->actingAs($this->user)->post(route('meetings.store'), [
        'title' => 'New Meeting',
        'summary' => 'A test meeting',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('minutes_of_meetings', [
        'title' => 'New Meeting',
        'organization_id' => $this->org->id,
    ]);
});

test('user can view a meeting', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('meetings.show', $meeting));

    $response->assertSuccessful();
    $response->assertSee($meeting->title);
});

test('user can finalize a draft meeting', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->post(route('meetings.finalize', $meeting));

    $response->assertRedirect(route('meetings.show', $meeting));
    expect($meeting->fresh()->status)->toBe(MeetingStatus::Finalized);
});

test('user cannot see meetings from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['current_organization_id' => $otherOrg->id]);

    MinutesOfMeeting::factory()->create([
        'organization_id' => $otherOrg->id,
        'created_by' => $otherUser->id,
        'title' => 'Secret Meeting',
        'meeting_date' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('meetings.index'));

    $response->assertSuccessful();
    $response->assertDontSee('Secret Meeting');
});

test('guest cannot access meetings', function () {
    $response = $this->get(route('meetings.index'));

    $response->assertRedirect(route('login'));
});
