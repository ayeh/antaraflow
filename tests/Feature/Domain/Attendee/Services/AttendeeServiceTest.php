<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Attendee\Models\AttendeeGroup;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Attendee\Services\AttendeeService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\AttendeeRole;
use App\Support\Enums\RsvpStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(AttendeeService::class);
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => 'owner']);
    $this->actingAs($this->user);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

test('can add attendee to meeting', function () {
    $attendee = $this->service->addAttendee($this->meeting, [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'role' => 'participant',
    ]);

    expect($attendee)->toBeInstanceOf(MomAttendee::class)
        ->and($attendee->name)->toBe('John Doe')
        ->and($attendee->email)->toBe('john@example.com')
        ->and($attendee->role)->toBe(AttendeeRole::Participant)
        ->and($attendee->minutes_of_meeting_id)->toBe($this->meeting->id);
});

test('can add external attendee', function () {
    $attendee = $this->service->addAttendee($this->meeting, [
        'name' => 'External Guest',
        'email' => 'guest@external.com',
        'role' => 'observer',
        'is_external' => true,
    ]);

    expect($attendee->is_external)->toBeTrue()
        ->and($attendee->role)->toBe(AttendeeRole::Observer);
});

test('can update RSVP status', function () {
    $attendee = MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'rsvp_status' => RsvpStatus::Pending,
    ]);

    $updated = $this->service->updateRsvp($attendee, RsvpStatus::Accepted);

    expect($updated->rsvp_status)->toBe(RsvpStatus::Accepted);
});

test('can mark attendance', function () {
    $attendee = MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'is_present' => false,
    ]);

    $updated = $this->service->markPresent($attendee);

    expect($updated->is_present)->toBeTrue();

    $updated = $this->service->markPresent($attendee, false);

    expect($updated->is_present)->toBeFalse();
});

test('can bulk invite from group', function () {
    $group = AttendeeGroup::factory()->create([
        'organization_id' => $this->org->id,
        'default_members' => [
            ['name' => 'Alice', 'email' => 'alice@example.com', 'role' => 'participant'],
            ['name' => 'Bob', 'email' => 'bob@example.com', 'role' => 'presenter'],
        ],
    ]);

    $created = $this->service->bulkInviteFromGroup($this->meeting, $group);

    expect($created)->toHaveCount(2);
    $this->assertDatabaseHas('mom_attendees', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'email' => 'alice@example.com',
    ]);
    $this->assertDatabaseHas('mom_attendees', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'email' => 'bob@example.com',
    ]);
});

test('bulk invite skips existing attendees', function () {
    MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'email' => 'alice@example.com',
        'name' => 'Alice',
    ]);

    $group = AttendeeGroup::factory()->create([
        'organization_id' => $this->org->id,
        'default_members' => [
            ['name' => 'Alice', 'email' => 'alice@example.com', 'role' => 'participant'],
            ['name' => 'Charlie', 'email' => 'charlie@example.com', 'role' => 'participant'],
        ],
    ]);

    $created = $this->service->bulkInviteFromGroup($this->meeting, $group);

    expect($created)->toHaveCount(1);
    expect($created->first()->email)->toBe('charlie@example.com');
});
