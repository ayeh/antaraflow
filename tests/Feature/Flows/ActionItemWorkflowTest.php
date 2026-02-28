<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
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
});

test('action item lifecycle: create, update, carry forward', function () {
    $response = $this->actingAs($this->user)
        ->post(route('meetings.action-items.store', $this->meeting), [
            'title' => 'Review PR #42',
            'description' => 'Review the pull request and provide feedback.',
            'priority' => 'high',
        ]);

    $response->assertRedirect(route('meetings.action-items.index', $this->meeting));

    $item = ActionItem::query()->where('title', 'Review PR #42')->first();
    expect($item)->not->toBeNull();
    expect($item->status)->toBe(ActionItemStatus::Open);

    $this->actingAs($this->user)
        ->put(route('meetings.action-items.update', [$this->meeting, $item]), [
            'title' => 'Review PR #42',
            'status' => 'in_progress',
        ])
        ->assertRedirect();

    $item->refresh();
    expect($item->status)->toBe(ActionItemStatus::InProgress);

    $newMeeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('meetings.action-items.carry-forward', [$this->meeting, $item]), [
            'new_meeting_id' => $newMeeting->id,
        ])
        ->assertRedirect(route('meetings.action-items.index', $this->meeting));

    $item->refresh();
    expect($item->status)->toBe(ActionItemStatus::CarriedForward);

    $carriedItem = ActionItem::query()
        ->where('carried_from_id', $item->id)
        ->where('minutes_of_meeting_id', $newMeeting->id)
        ->first();

    expect($carriedItem)->not->toBeNull();
    expect($carriedItem->title)->toBe('Review PR #42');
    expect($carriedItem->status)->toBe(ActionItemStatus::Open);
});

test('action items dashboard shows items across meetings', function () {
    $meeting2 = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $meeting2->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    $response = $this->actingAs($this->user)->get(route('action-items.dashboard'));

    $response->assertSuccessful();
    expect($response->viewData('actionItems'))->toHaveCount(2);
});
