<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Project\Models\Project;
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
        'meeting_date' => '2026-03-15',
        'prepared_by' => 'John Doe',
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

test('creates meeting with new wizard fields', function () {
    $project = Project::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->post(route('meetings.store'), [
        'title' => 'Wizard Meeting',
        'project_id' => $project->id,
        'meeting_date' => '2026-04-01',
        'start_time' => '09:00',
        'end_time' => '10:30',
        'location' => 'Conference Room A',
        'language' => 'en',
        'prepared_by' => 'Jane Smith',
        'share_with_client' => true,
    ]);

    $response->assertRedirect();

    $meeting = MinutesOfMeeting::query()
        ->where('title', 'Wizard Meeting')
        ->first();

    expect($meeting)->not->toBeNull()
        ->and($meeting->project_id)->toBe($project->id)
        ->and($meeting->meeting_date->format('Y-m-d'))->toBe('2026-04-01')
        ->and($meeting->start_time->format('H:i'))->toBe('09:00')
        ->and($meeting->end_time->format('H:i'))->toBe('10:30')
        ->and($meeting->location)->toBe('Conference Room A')
        ->and($meeting->language)->toBe('en')
        ->and($meeting->prepared_by)->toBe('Jane Smith')
        ->and($meeting->share_with_client)->toBeTrue()
        ->and($meeting->mom_number)->not->toBeNull()
        ->and($meeting->status)->toBe(MeetingStatus::Draft);
});

test('validates create meeting required fields', function () {
    $response = $this->actingAs($this->user)->post(route('meetings.store'), []);

    $response->assertSessionHasErrors(['title', 'meeting_date', 'prepared_by']);
});

test('validates end_time must be after start_time', function () {
    $response = $this->actingAs($this->user)->post(route('meetings.store'), [
        'title' => 'Time Test Meeting',
        'meeting_date' => '2026-04-01',
        'prepared_by' => 'John Doe',
        'start_time' => '14:00',
        'end_time' => '13:00',
    ]);

    $response->assertSessionHasErrors(['end_time']);
});
