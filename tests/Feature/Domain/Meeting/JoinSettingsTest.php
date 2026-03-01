<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

test('creating a meeting also creates join settings', function () {
    $this->actingAs($this->user)->post(route('meetings.store'), [
        'title' => 'Join Settings Test Meeting',
    ]);

    $meeting = MinutesOfMeeting::query()
        ->where('title', 'Join Settings Test Meeting')
        ->firstOrFail();

    $this->assertDatabaseHas('mom_join_settings', [
        'minutes_of_meeting_id' => $meeting->id,
    ]);
});

test('join settings defaults are correct', function () {
    $this->actingAs($this->user)->post(route('meetings.store'), [
        'title' => 'Defaults Test Meeting',
    ]);

    $meeting = MinutesOfMeeting::query()
        ->where('title', 'Defaults Test Meeting')
        ->firstOrFail();

    $this->assertDatabaseHas('mom_join_settings', [
        'minutes_of_meeting_id' => $meeting->id,
        'allow_external_join' => false,
        'require_rsvp' => false,
        'auto_notify' => true,
    ]);
});

test('updating join settings persists', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->put(route('meetings.update', $meeting), [
        'title' => $meeting->title,
        'allow_external_join' => 1,
        'require_rsvp' => 1,
    ]);

    $this->assertDatabaseHas('mom_join_settings', [
        'minutes_of_meeting_id' => $meeting->id,
        'allow_external_join' => true,
        'require_rsvp' => true,
        'auto_notify' => true,
    ]);
});

test('join settings are shown in edit form', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('meetings.edit', $meeting));

    $response->assertSuccessful();
    $response->assertSee('Allow External Join');
    $response->assertSee('Require RSVP');
    $response->assertSee('Auto-notify Attendees');
});
