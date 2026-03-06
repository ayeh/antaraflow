<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\AI\Models\MomExtraction;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomManualNote;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Offline Test Meeting',
    ]);
});

test('offline data endpoint returns full meeting JSON', function () {
    MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'user_id' => $this->user->id,
        'name' => $this->user->name,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
        'title' => 'Test Action Item',
    ]);

    MomExtraction::factory()->summary()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
    ]);

    MomManualNote::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'title' => 'Test Note',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('meetings.offline-data', $this->meeting));

    $response->assertOk()
        ->assertJsonPath('id', $this->meeting->id)
        ->assertJsonPath('title', 'Offline Test Meeting')
        ->assertJsonCount(1, 'attendees')
        ->assertJsonCount(1, 'action_items')
        ->assertJsonCount(1, 'extractions')
        ->assertJsonCount(1, 'notes')
        ->assertJsonStructure([
            'id', 'title', 'mom_number', 'status', 'meeting_date',
            'location', 'summary', 'content', 'cached_at',
            'attendees' => [['id', 'name', 'email', 'is_present', 'rsvp_status']],
            'action_items' => [['id', 'title', 'description', 'status', 'due_date', 'assigned_to']],
            'extractions' => [['id', 'type', 'content', 'created_at']],
            'notes' => [['id', 'title', 'content', 'created_by', 'created_at']],
        ]);
});

test('sync endpoint processes queued notes', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('offline.sync'), [
            'actions' => [
                [
                    'type' => 'note',
                    'meeting_id' => $this->meeting->id,
                    'payload' => [
                        'title' => 'Offline Note',
                        'content' => 'Created while offline',
                    ],
                    'offline_id' => 'offline_123_abc',
                ],
            ],
        ]);

    $response->assertOk()
        ->assertJsonCount(1, 'synced')
        ->assertJsonPath('synced.0.offline_id', 'offline_123_abc')
        ->assertJsonPath('synced.0.status', 'synced');

    $this->assertDatabaseHas('mom_manual_notes', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'title' => 'Offline Note',
        'content' => 'Created while offline',
        'created_by' => $this->user->id,
    ]);
});

test('sync endpoint processes queued comments', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('offline.sync'), [
            'actions' => [
                [
                    'type' => 'comment',
                    'meeting_id' => $this->meeting->id,
                    'payload' => [
                        'body' => 'Offline comment text',
                    ],
                    'offline_id' => 'offline_456_def',
                ],
            ],
        ]);

    $response->assertOk()
        ->assertJsonCount(1, 'synced')
        ->assertJsonPath('synced.0.offline_id', 'offline_456_def')
        ->assertJsonPath('synced.0.status', 'synced');

    $this->assertDatabaseHas('comments', [
        'commentable_type' => MinutesOfMeeting::class,
        'commentable_id' => $this->meeting->id,
        'body' => 'Offline comment text',
        'user_id' => $this->user->id,
    ]);
});

test('offline data requires authentication', function () {
    $response = $this->getJson(route('meetings.offline-data', $this->meeting));

    $response->assertUnauthorized();
});

test('sync rejects invalid action types', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('offline.sync'), [
            'actions' => [
                [
                    'type' => 'delete',
                    'meeting_id' => $this->meeting->id,
                    'payload' => ['id' => 1],
                    'offline_id' => 'offline_789_ghi',
                ],
            ],
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['actions.0.type']);
});
