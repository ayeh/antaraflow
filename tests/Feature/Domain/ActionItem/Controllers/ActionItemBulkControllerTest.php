<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
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
    $this->items = ActionItem::factory()->count(3)->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
        'priority' => ActionItemPriority::Medium,
    ]);
});

test('bulk status update changes all selected items', function () {
    $ids = $this->items->pluck('id')->toArray();

    $response = $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => $ids,
            'action' => 'status',
            'value' => 'in_progress',
        ]);

    $response->assertOk()->assertJson(['updated' => 3]);

    foreach ($ids as $id) {
        $this->assertDatabaseHas('action_items', ['id' => $id, 'status' => 'in_progress']);
    }
});

test('bulk status update to completed sets completed_at', function () {
    $ids = $this->items->pluck('id')->toArray();

    $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => $ids,
            'action' => 'status',
            'value' => 'completed',
        ]);

    foreach ($ids as $id) {
        $this->assertDatabaseHas('action_items', ['id' => $id, 'status' => 'completed']);
        expect(ActionItem::find($id)->completed_at)->not->toBeNull();
    }
});

test('bulk priority update changes all selected items', function () {
    $ids = $this->items->pluck('id')->toArray();

    $response = $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => $ids,
            'action' => 'priority',
            'value' => 'high',
        ]);

    $response->assertOk()->assertJson(['updated' => 3]);

    foreach ($ids as $id) {
        $this->assertDatabaseHas('action_items', ['id' => $id, 'priority' => 'high']);
    }
});

test('bulk delete removes all selected items', function () {
    $ids = $this->items->pluck('id')->toArray();

    $response = $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => $ids,
            'action' => 'delete',
        ]);

    $response->assertOk()->assertJson(['updated' => 3]);

    foreach ($ids as $id) {
        $this->assertSoftDeleted('action_items', ['id' => $id]);
    }
});

test('bulk action ignores ids from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    $otherItem = ActionItem::factory()->create([
        'organization_id' => $otherOrg->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => [$otherItem->id],
            'action' => 'status',
            'value' => 'completed',
        ]);

    $response->assertOk()->assertJson(['updated' => 0]);
    $this->assertDatabaseHas('action_items', ['id' => $otherItem->id, 'status' => 'open']);
});

test('bulk action requires ids', function () {
    $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), ['action' => 'status', 'value' => 'completed'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ids']);
});

test('bulk status requires value', function () {
    $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => $this->items->pluck('id')->toArray(),
            'action' => 'status',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

test('bulk delete does not require value', function () {
    $this->actingAs($this->user)
        ->postJson(route('action-items.bulk'), [
            'ids' => $this->items->pluck('id')->toArray(),
            'action' => 'delete',
        ])
        ->assertOk();
});

test('guest cannot perform bulk actions', function () {
    $this->postJson(route('action-items.bulk'), [
        'ids' => $this->items->pluck('id')->toArray(),
        'action' => 'status',
        'value' => 'completed',
    ])->assertUnauthorized();
});
