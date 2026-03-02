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

test('create all tasks marks open action items', function () {
    $item1 = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    $item2 = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::InProgress,
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('meetings.action-items.create-all-tasks', $this->meeting));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect($item1->fresh()->metadata)->toHaveKey('tasks_created_at');
    expect($item2->fresh()->metadata)->toHaveKey('tasks_created_at');
});

test('create all tasks skips completed and cancelled items', function () {
    $completed = ActionItem::factory()->completed()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    $cancelled = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Cancelled,
    ]);

    $open = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    $this->actingAs($this->user)
        ->post(route('meetings.action-items.create-all-tasks', $this->meeting));

    expect($completed->fresh()->metadata)->toBeNull();
    expect($cancelled->fresh()->metadata)->toBeNull();
    expect($open->fresh()->metadata)->toHaveKey('tasks_created_at');
});

test('create all tasks skips already-tasked items', function () {
    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
        'metadata' => ['tasks_created_at' => now()->toIso8601String()],
    ]);

    $response = $this->actingAs($this->user)
        ->post(route('meetings.action-items.create-all-tasks', $this->meeting));

    $response->assertRedirect();
    $response->assertSessionHas('success', '0 action item(s) marked as tasks created.');
});

test('create all tasks records audit log entries', function () {
    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    $this->actingAs($this->user)
        ->post(route('meetings.action-items.create-all-tasks', $this->meeting));

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'tasks_created',
        'auditable_type' => ActionItem::class,
    ]);
});

test('guest cannot create all tasks', function () {
    $response = $this->post(route('meetings.action-items.create-all-tasks', $this->meeting));

    $response->assertRedirect(route('login'));
});
