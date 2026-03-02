<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Services\ActionItemService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->service = app()->make(ActionItemService::class);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

it('create() sets default status to Open', function () {
    $item = $this->service->create([
        'title' => 'Test Action Item',
        'priority' => ActionItemPriority::Medium,
    ], $this->meeting, $this->user);

    expect($item->status)->toBe(ActionItemStatus::Open);
});

it('create() sets minutes_of_meeting_id from the given meeting', function () {
    $item = $this->service->create([
        'title' => 'Test Action Item',
        'priority' => ActionItemPriority::Medium,
    ], $this->meeting, $this->user);

    expect($item->minutes_of_meeting_id)->toBe($this->meeting->id);
});

it('create() sets organization_id from the meeting', function () {
    $item = $this->service->create([
        'title' => 'Test Action Item',
        'priority' => ActionItemPriority::Medium,
    ], $this->meeting, $this->user);

    expect($item->organization_id)->toBe($this->org->id);
});

it('create() sets created_by to the given user', function () {
    $item = $this->service->create([
        'title' => 'Test Action Item',
        'priority' => ActionItemPriority::Medium,
    ], $this->meeting, $this->user);

    expect($item->created_by)->toBe($this->user->id);
});

it('update() persists changed fields', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'title' => 'Original Title',
        'priority' => ActionItemPriority::Low,
    ]);

    $updated = $this->service->update($item, [
        'title' => 'Updated Title',
        'priority' => ActionItemPriority::High,
    ], $this->user);

    expect($updated->title)->toBe('Updated Title');
    expect($updated->priority)->toBe(ActionItemPriority::High);
});

it('update() records a history entry for each changed field', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'title' => 'Original Title',
    ]);

    $this->service->update($item, ['title' => 'Changed Title'], $this->user);

    $history = $item->histories()->where('field_changed', 'title')->first();
    expect($history)->not->toBeNull();
    expect($history->old_value)->toBe('Original Title');
    expect($history->new_value)->toBe('Changed Title');
    expect($history->changed_by)->toBe($this->user->id);
});

it('update() does not record history for unchanged fields', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'title' => 'Same Title',
    ]);

    $this->service->update($item, ['title' => 'Same Title'], $this->user);

    expect($item->histories()->count())->toBe(0);
});

it('changeStatus() updates the status field', function () {
    $item = ActionItem::factory()->open()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    $updated = $this->service->changeStatus($item, ActionItemStatus::InProgress, $this->user);

    expect($updated->status)->toBe(ActionItemStatus::InProgress);
    expect($item->fresh()->status)->toBe(ActionItemStatus::InProgress);
});

it('changeStatus() sets completed_at when status is Completed', function () {
    $item = ActionItem::factory()->open()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    $updated = $this->service->changeStatus($item, ActionItemStatus::Completed, $this->user);

    expect($updated->completed_at)->not->toBeNull();
    expect($updated->status)->toBe(ActionItemStatus::Completed);
});

it('changeStatus() records a history entry with old and new values', function () {
    $item = ActionItem::factory()->open()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->changeStatus($item, ActionItemStatus::Completed, $this->user, 'Done!');

    $history = $item->histories()->first();
    expect($history->field_changed)->toBe('status');
    expect($history->old_value)->toBe(ActionItemStatus::Open->value);
    expect($history->new_value)->toBe(ActionItemStatus::Completed->value);
    expect($history->comment)->toBe('Done!');
});

it('carryForward() creates a new action item with Open status', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'title' => 'Original Item',
    ]);

    $newMeeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $newItem = $this->service->carryForward($item, $newMeeting, $this->user);

    expect($newItem->status)->toBe(ActionItemStatus::Open);
    expect($newItem->title)->toBe('Original Item');
    expect($newItem->minutes_of_meeting_id)->toBe($newMeeting->id);
    expect($newItem->carried_from_id)->toBe($item->id);
});

it('carryForward() marks the original item as CarriedForward', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    $newMeeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->service->carryForward($item, $newMeeting, $this->user);

    expect($item->fresh()->status)->toBe(ActionItemStatus::CarriedForward);
});
