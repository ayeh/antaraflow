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

test('full meeting lifecycle: create, edit, add attendees, finalize, approve', function () {
    $response = $this->actingAs($this->user)->post(route('meetings.store'), [
        'title' => 'Sprint Planning',
        'meeting_date' => now()->addDay()->format('Y-m-d'),
        'location' => 'Conference Room A',
        'prepared_by' => 'Test User',
    ]);
    $response->assertRedirect();

    $meeting = MinutesOfMeeting::query()->latest('id')->first();
    expect($meeting)->not->toBeNull();
    expect($meeting->status)->toBe(MeetingStatus::Draft);
    expect($meeting->title)->toBe('Sprint Planning');

    $this->actingAs($this->user)->put(route('meetings.update', $meeting), [
        'title' => 'Sprint Planning v2',
        'content' => 'Discussed sprint goals and backlog prioritization.',
    ])->assertRedirect();

    $meeting->refresh();
    expect($meeting->title)->toBe('Sprint Planning v2');

    $this->actingAs($this->user)->post(
        route('meetings.attendees.store', $meeting),
        [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'participant',
        ]
    )->assertRedirect();

    expect($meeting->attendees()->count())->toBe(1);

    $this->actingAs($this->user)->post(route('meetings.finalize', $meeting))
        ->assertRedirect();

    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Finalized);
    expect($meeting->versions()->count())->toBeGreaterThanOrEqual(1);

    $this->actingAs($this->user)->post(route('meetings.approve', $meeting))
        ->assertRedirect();

    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Approved);
});

test('cannot edit an approved meeting', function () {
    $meeting = MinutesOfMeeting::factory()->approved()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->put(route('meetings.update', $meeting), [
        'title' => 'Updated title',
    ])->assertForbidden();
});

test('viewer cannot create or finalize meetings', function () {
    $viewer = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($viewer, ['role' => UserRole::Viewer->value]);

    $this->actingAs($viewer)->post(route('meetings.store'), [
        'title' => 'Should not work',
        'meeting_date' => '2026-04-01',
        'prepared_by' => 'Viewer User',
    ])->assertForbidden();

    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($viewer)->post(route('meetings.finalize', $meeting))
        ->assertForbidden();
});
