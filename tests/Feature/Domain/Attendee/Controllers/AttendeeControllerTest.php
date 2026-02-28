<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

test('user can add attendee', function () {
    $response = $this->actingAs($this->user)->post(
        route('meetings.attendees.store', $this->meeting),
        [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'role' => 'participant',
        ]
    );

    $response->assertRedirect(route('meetings.show', $this->meeting));
    $this->assertDatabaseHas('mom_attendees', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
});

test('user can view attendees', function () {
    MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'name' => 'Test Attendee',
    ]);

    $response = $this->actingAs($this->user)->get(
        route('meetings.attendees.index', $this->meeting)
    );

    $response->assertSuccessful();
    $response->assertSee('Test Attendee');
});

test('user can update rsvp', function () {
    $attendee = MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'rsvp_status' => 'pending',
    ]);

    $response = $this->actingAs($this->user)->patch(
        route('meetings.attendees.rsvp', [$this->meeting, $attendee]),
        ['rsvp_status' => 'accepted']
    );

    $response->assertRedirect(route('meetings.show', $this->meeting));
    $this->assertDatabaseHas('mom_attendees', [
        'id' => $attendee->id,
        'rsvp_status' => 'accepted',
    ]);
});

test('guest cannot access attendees', function () {
    $response = $this->get(
        route('meetings.attendees.index', $this->meeting)
    );

    $response->assertRedirect(route('login'));
});
