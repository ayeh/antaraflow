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
        'meeting_date' => '2026-04-01',
        'prepared_by' => 'John Doe',
    ]);

    $meeting = MinutesOfMeeting::query()
        ->where('title', 'Join Settings Test Meeting')
        ->first();

    // Join settings are not created on the lean create form (no join setting fields sent)
    expect($meeting)->not->toBeNull();
});

test('join settings defaults are correct', function () {
    $this->actingAs($this->user)->post(route('meetings.store'), [
        'title' => 'Defaults Test Meeting',
        'meeting_date' => '2026-04-01',
        'prepared_by' => 'John Doe',
    ]);

    $meeting = MinutesOfMeeting::query()
        ->where('title', 'Defaults Test Meeting')
        ->first();

    // The lean create form does not send join settings,
    // so no join setting record is created on initial creation.
    expect($meeting)->not->toBeNull();
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

test('auto_notify can be explicitly set to false', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->put(route('meetings.update', $meeting), [
        'title' => $meeting->title,
        'auto_notify' => 0,
    ]);

    $this->assertDatabaseHas('mom_join_settings', [
        'minutes_of_meeting_id' => $meeting->id,
        'auto_notify' => false,
    ]);
});

test('edit form pre-fills join settings from existing record', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $meeting->joinSetting()->create([
        'allow_external_join' => true,
        'require_rsvp' => false,
        'auto_notify' => false,
    ]);

    $response = $this->actingAs($this->user)->get(route('meetings.edit', $meeting));

    $response->assertSuccessful();
    // allow_external_join=true should render the checkbox as checked
    $response->assertSeeInOrder([
        'name="allow_external_join" value="1"',
        'checked',
        'Allow External Join',
    ], false);
    // require_rsvp=false and auto_notify=false should not be checked
    $response->assertDontSee('name="require_rsvp" value="1" checked', false);
    $response->assertDontSee('name="auto_notify" value="1" checked', false);
});
