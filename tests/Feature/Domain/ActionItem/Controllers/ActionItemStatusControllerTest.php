<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Models\ActionItemHistory;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\ActionItemStatus;
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
    ]);
    $this->item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);
});

test('user can update action item status via patch endpoint', function () {
    $response = $this->actingAs($this->user)
        ->patchJson(route('meetings.action-items.status', [$this->meeting, $this->item]), [
            'status' => 'in_progress',
        ]);

    $response->assertOk()
        ->assertJsonStructure(['id', 'status', 'status_label', 'status_color_class', 'completed_at']);

    expect($response->json('status'))->toBe('in_progress')
        ->and($response->json('status_label'))->toBe('In Progress');

    $this->assertDatabaseHas('action_items', [
        'id' => $this->item->id,
        'status' => 'in_progress',
    ]);
});

test('completing a task via status endpoint sets completed_at', function () {
    $response = $this->actingAs($this->user)
        ->patchJson(route('meetings.action-items.status', [$this->meeting, $this->item]), [
            'status' => 'completed',
        ]);

    $response->assertOk();
    expect($response->json('completed_at'))->not->toBeNull();

    $this->assertDatabaseHas('action_items', [
        'id' => $this->item->id,
        'status' => 'completed',
    ]);
});

test('status change with comment is saved in history', function () {
    $this->actingAs($this->user)
        ->patchJson(route('meetings.action-items.status', [$this->meeting, $this->item]), [
            'status' => 'in_progress',
            'comment' => 'Starting this now',
        ]);

    $this->assertDatabaseHas('action_item_histories', [
        'action_item_id' => $this->item->id,
        'field_changed' => 'status',
        'old_value' => 'open',
        'new_value' => 'in_progress',
        'comment' => 'Starting this now',
    ]);
});

test('status change without comment creates history with null comment', function () {
    $this->actingAs($this->user)
        ->patchJson(route('meetings.action-items.status', [$this->meeting, $this->item]), [
            'status' => 'in_progress',
        ]);

    expect(ActionItemHistory::where('action_item_id', $this->item->id)->first()->comment)->toBeNull();
});

test('invalid status returns 422', function () {
    $response = $this->actingAs($this->user)
        ->patchJson(route('meetings.action-items.status', [$this->meeting, $this->item]), [
            'status' => 'not_a_real_status',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('missing status returns 422', function () {
    $response = $this->actingAs($this->user)
        ->patchJson(route('meetings.action-items.status', [$this->meeting, $this->item]), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

test('guest cannot update action item status', function () {
    $response = $this->patchJson(
        route('meetings.action-items.status', [$this->meeting, $this->item]),
        ['status' => 'in_progress']
    );

    $response->assertUnauthorized();
});
