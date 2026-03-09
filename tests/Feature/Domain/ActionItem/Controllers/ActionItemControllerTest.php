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

test('user can create action item', function () {
    $response = $this->actingAs($this->user)
        ->post(route('meetings.action-items.store', $this->meeting), [
            'title' => 'New action item',
            'description' => 'Action item description',
            'priority' => 'high',
        ]);

    $response->assertRedirect(route('meetings.action-items.index', $this->meeting));
    $response->assertSessionHas('success', 'Action item created successfully.');

    $this->assertDatabaseHas('action_items', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'title' => 'New action item',
        'priority' => 'high',
    ]);
});

test('user can view action items for meeting', function () {
    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'title' => 'Test item',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.action-items.index', $this->meeting));

    $response->assertSuccessful();
    $response->assertViewHas('actionItems');
});

test('user can carry forward action item', function () {
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

    $response = $this->actingAs($this->user)
        ->post(route('meetings.action-items.carry-forward', [$this->meeting, $item]), [
            'new_meeting_id' => $newMeeting->id,
        ]);

    $response->assertRedirect(route('meetings.action-items.index', $this->meeting));
    $response->assertSessionHas('success', 'Action item carried forward successfully.');

    $item->refresh();
    expect($item->status)->toBe(ActionItemStatus::CarriedForward);

    $this->assertDatabaseHas('action_items', [
        'carried_from_id' => $item->id,
        'minutes_of_meeting_id' => $newMeeting->id,
    ]);
});

test('action items are scoped to organization', function () {
    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['current_organization_id' => $otherOrg->id]);
    $otherOrg->members()->attach($otherUser, ['role' => UserRole::Owner->value]);

    $otherMeeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $otherOrg->id,
        'created_by' => $otherUser->id,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $otherOrg->id,
        'minutes_of_meeting_id' => $otherMeeting->id,
        'created_by' => $otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.action-items.index', $this->meeting));

    $response->assertSuccessful();
    expect($response->viewData('actionItems'))->toHaveCount(1);
});

test('guest cannot access action items', function () {
    $response = $this->get(route('meetings.action-items.index', $this->meeting));

    $response->assertRedirect(route('login'));
});

test('user can view action items dashboard', function () {
    ActionItem::factory()->count(2)->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('action-items.dashboard'));

    $response->assertSuccessful();
    $response->assertViewHas('actionItems');
});

test('user can update action item', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'title' => 'Original title',
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('meetings.action-items.update', [$this->meeting, $item]), [
            'title' => 'Updated title',
        ]);

    $response->assertRedirect(route('meetings.action-items.show', [$this->meeting, $item]));

    $this->assertDatabaseHas('action_items', [
        'id' => $item->id,
        'title' => 'Updated title',
    ]);
});

test('user can delete action item', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('meetings.action-items.destroy', [$this->meeting, $item]));

    $response->assertRedirect(route('meetings.action-items.index', $this->meeting));

    $this->assertSoftDeleted('action_items', ['id' => $item->id]);
});

test('show returns json when requested with accept header', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('meetings.action-items.show', [$this->meeting, $item]));

    $response->assertOk()
        ->assertJsonStructure([
            'id', 'title', 'description', 'status', 'priority',
            'due_date', 'assigned_to', 'meeting_id',
            'show_url', 'update_url', 'status_url',
            'users' => [['id', 'name']],
            'history',
        ]);

    expect($response->json('id'))->toBe($item->id);
});

test('update returns json when requested with accept header', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    $response = $this->actingAs($this->user)
        ->putJson(route('meetings.action-items.update', [$this->meeting, $item]), [
            'title' => 'Updated Title',
            'priority' => 'high',
        ]);

    $response->assertOk()
        ->assertJsonStructure([
            'id', 'title', 'status', 'status_label', 'status_color_class',
            'priority', 'priority_label', 'priority_color_class',
            'due_date', 'due_date_formatted', 'assigned_to', 'assigned_to_name',
        ]);

    expect($response->json('title'))->toBe('Updated Title')
        ->and($response->json('priority'))->toBe('high');
});

test('update sets completed_at when status changes to completed', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Open,
    ]);

    $this->actingAs($this->user)
        ->putJson(route('meetings.action-items.update', [$this->meeting, $item]), [
            'status' => 'completed',
        ]);

    expect(ActionItem::find($item->id)->completed_at)->not->toBeNull();
});

test('update clears completed_at when status changes away from completed', function () {
    $item = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $this->meeting->id,
        'created_by' => $this->user->id,
        'status' => ActionItemStatus::Completed,
        'completed_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->putJson(route('meetings.action-items.update', [$this->meeting, $item]), [
            'status' => 'open',
        ]);

    expect(ActionItem::find($item->id)->completed_at)->toBeNull();
});
