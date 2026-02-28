<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Models\ActionItemHistory;
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
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user);
});

test('can create action item for meeting', function () {
    $service = app(ActionItemService::class);

    $item = $service->create([
        'title' => 'Review pull request',
        'description' => 'Review the feature branch PR',
        'priority' => ActionItemPriority::High,
    ], $this->meeting, $this->user);

    expect($item)->toBeInstanceOf(ActionItem::class)
        ->and($item->title)->toBe('Review pull request')
        ->and($item->description)->toBe('Review the feature branch PR')
        ->and($item->priority)->toBe(ActionItemPriority::High)
        ->and($item->status)->toBe(ActionItemStatus::Open)
        ->and($item->organization_id)->toBe($this->org->id)
        ->and($item->minutes_of_meeting_id)->toBe($this->meeting->id)
        ->and($item->created_by)->toBe($this->user->id);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'created',
        'auditable_type' => ActionItem::class,
        'auditable_id' => $item->id,
    ]);
});

test('can update action item and create history', function () {
    $service = app(ActionItemService::class);

    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'title' => 'Original title',
        'priority' => ActionItemPriority::Low,
    ]);

    $updated = $service->update($item, [
        'title' => 'Updated title',
        'priority' => 'high',
    ], $this->user);

    expect($updated->title)->toBe('Updated title');

    $histories = ActionItemHistory::query()
        ->where('action_item_id', $item->id)
        ->get();

    expect($histories)->toHaveCount(2);

    $titleHistory = $histories->firstWhere('field_changed', 'title');
    expect($titleHistory->old_value)->toBe('Original title')
        ->and($titleHistory->new_value)->toBe('Updated title');
});

test('status change creates history entry', function () {
    $service = app(ActionItemService::class);

    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    $service->changeStatus($item, ActionItemStatus::InProgress, $this->user, 'Starting work');

    $this->assertDatabaseHas('action_item_histories', [
        'action_item_id' => $item->id,
        'field_changed' => 'status',
        'old_value' => 'open',
        'new_value' => 'in_progress',
        'comment' => 'Starting work',
    ]);
});

test('completing sets completed_at', function () {
    $service = app(ActionItemService::class);

    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::InProgress,
    ]);

    $updated = $service->changeStatus($item, ActionItemStatus::Completed, $this->user);

    expect($updated->status)->toBe(ActionItemStatus::Completed)
        ->and($updated->completed_at)->not->toBeNull();
});

test('can carry forward action item to new meeting', function () {
    $service = app(ActionItemService::class);

    $newMeeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
        'title' => 'Carry me forward',
        'priority' => ActionItemPriority::High,
    ]);

    $newItem = $service->carryForward($item, $newMeeting, $this->user);

    expect($newItem->title)->toBe('Carry me forward')
        ->and($newItem->priority)->toBe(ActionItemPriority::High)
        ->and($newItem->status)->toBe(ActionItemStatus::Open)
        ->and($newItem->carried_from_id)->toBe($item->id)
        ->and($newItem->minutes_of_meeting_id)->toBe($newMeeting->id)
        ->and($newItem->assigned_to)->toBe($this->user->id);
});

test('carry forward marks original as carried_forward', function () {
    $service = app(ActionItemService::class);

    $newMeeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    $service->carryForward($item, $newMeeting, $this->user);

    $item->refresh();
    expect($item->status)->toBe(ActionItemStatus::CarriedForward);

    $this->assertDatabaseHas('action_item_histories', [
        'action_item_id' => $item->id,
        'field_changed' => 'status',
        'old_value' => 'open',
        'new_value' => 'carried_forward',
    ]);
});

test('overdue items are identified correctly', function () {
    $service = app(ActionItemService::class);

    ActionItem::factory()->overdue()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'due_date' => now()->addDays(7),
        'status' => ActionItemStatus::Open,
    ]);

    ActionItem::factory()->completed()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'due_date' => now()->subDays(5),
    ]);

    $overdue = $service->getOverdueItems($this->org->id);

    expect($overdue)->toHaveCount(1);
});

test('dashboard returns items for organization', function () {
    $service = app(ActionItemService::class);

    ActionItem::factory()->count(3)->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Cancelled,
    ]);

    $items = $service->getDashboard($this->org->id);

    expect($items)->toHaveCount(3);
});

test('dashboard filters by assignee', function () {
    $service = app(ActionItemService::class);

    $otherUser = User::factory()->create(['current_organization_id' => $this->org->id]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'assigned_to' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'assigned_to' => $otherUser->id,
        'status' => ActionItemStatus::Open,
    ]);

    $myItems = $service->getDashboard($this->org->id, $this->user->id);
    $allItems = $service->getDashboard($this->org->id);

    expect($myItems)->toHaveCount(1)
        ->and($allItems)->toHaveCount(2);
});
