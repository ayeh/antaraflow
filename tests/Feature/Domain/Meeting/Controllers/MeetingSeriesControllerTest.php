<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MeetingSeries;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);
});

test('user can list meeting series', function () {
    MeetingSeries::factory()->create([
        'organization_id' => $this->org->id,
        'name' => 'Weekly Standup',
    ]);

    $response = $this->actingAs($this->user)->get(route('meeting-series.index'));

    $response->assertSuccessful();
    $response->assertSee('Weekly Standup');
});

test('manager can create a meeting series', function () {
    $response = $this->actingAs($this->user)->post(route('meeting-series.store'), [
        'name' => 'New Series',
        'recurrence_pattern' => 'weekly',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('meeting_series', [
        'name' => 'New Series',
        'organization_id' => $this->org->id,
    ]);
});

test('manager can update a meeting series', function () {
    $series = MeetingSeries::factory()->create([
        'organization_id' => $this->org->id,
        'recurrence_pattern' => 'weekly',
    ]);

    $response = $this->actingAs($this->user)->put(route('meeting-series.update', $series), [
        'name' => 'Updated Series',
        'recurrence_pattern' => 'monthly',
        'is_active' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('meeting_series', [
        'id' => $series->id,
        'name' => 'Updated Series',
        'recurrence_pattern' => 'monthly',
    ]);
});

test('manager can delete a meeting series', function () {
    $series = MeetingSeries::factory()->create([
        'organization_id' => $this->org->id,
    ]);

    $response = $this->actingAs($this->user)->delete(route('meeting-series.destroy', $series));

    $response->assertRedirect(route('meeting-series.index'));
    $this->assertDatabaseMissing('meeting_series', ['id' => $series->id]);
});

test('manager can generate meetings from a series', function () {
    $series = MeetingSeries::factory()->create([
        'organization_id' => $this->org->id,
        'recurrence_pattern' => 'weekly',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->post(route('meeting-series.generate', $series), [
        'count' => 3,
    ]);

    $response->assertRedirect(route('meeting-series.show', $series));
    $this->assertDatabaseCount('minutes_of_meetings', 3);
});
