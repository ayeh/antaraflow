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

it('cannot update an approved meeting', function () {
    $meeting = MinutesOfMeeting::factory()->approved()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('meetings.update', $meeting), [
        'title' => 'Attempted Edit',
    ]);

    $response->assertForbidden();
    expect($meeting->fresh()->title)->not->toBe('Attempted Edit');
});

it('can finalize a draft meeting', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->post(route('meetings.finalize', $meeting));

    $response->assertRedirect(route('meetings.show', $meeting));
    expect($meeting->fresh()->status)->toBe(MeetingStatus::Finalized);
});

it('can finalize an in-progress meeting', function () {
    $meeting = MinutesOfMeeting::factory()->inProgress()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->post(route('meetings.finalize', $meeting));

    $response->assertRedirect(route('meetings.show', $meeting));
    expect($meeting->fresh()->status)->toBe(MeetingStatus::Finalized);
});

it('cannot finalize an already-finalized meeting', function () {
    $meeting = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->post(route('meetings.finalize', $meeting))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($meeting->fresh()->status)->toBe(MeetingStatus::Finalized);
});

it('cannot finalize an approved meeting', function () {
    $meeting = MinutesOfMeeting::factory()->approved()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->post(route('meetings.finalize', $meeting))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($meeting->fresh()->status)->toBe(MeetingStatus::Approved);
});

it('can revert a finalized meeting back to draft', function () {
    $meeting = MinutesOfMeeting::factory()->finalized()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->post(route('meetings.revert', $meeting));

    $response->assertRedirect(route('meetings.show', $meeting));
    expect($meeting->fresh()->status)->toBe(MeetingStatus::Draft);
});
