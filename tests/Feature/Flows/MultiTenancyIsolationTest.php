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
    $this->org1 = Organization::factory()->create();
    $this->user1 = User::factory()->create(['current_organization_id' => $this->org1->id]);
    $this->org1->members()->attach($this->user1, ['role' => UserRole::Owner->value]);

    $this->org2 = Organization::factory()->create();
    $this->user2 = User::factory()->create(['current_organization_id' => $this->org2->id]);
    $this->org2->members()->attach($this->user2, ['role' => UserRole::Owner->value]);
});

test('users cannot see other organizations meetings', function () {
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org1->id,
        'created_by' => $this->user1->id,
        'title' => 'Org1 Secret Meeting',
        'meeting_date' => now(),
    ]);

    $response = $this->actingAs($this->user2)->get(route('meetings.index'));

    $response->assertSuccessful();
    $response->assertDontSee('Org1 Secret Meeting');
});

test('users cannot access other organizations meeting directly', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org1->id,
        'created_by' => $this->user1->id,
    ]);

    $this->actingAs($this->user2)
        ->get(route('meetings.show', $meeting))
        ->assertNotFound();
});

test('users cannot see other organizations action items in dashboard', function () {
    $meeting1 = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org1->id,
        'created_by' => $this->user1->id,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org1->id,
        'minutes_of_meeting_id' => $meeting1->id,
        'assigned_to' => $this->user1->id,
        'created_by' => $this->user1->id,
        'status' => ActionItemStatus::Open,
    ]);

    $meeting2 = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org2->id,
        'created_by' => $this->user2->id,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org2->id,
        'minutes_of_meeting_id' => $meeting2->id,
        'assigned_to' => $this->user2->id,
        'created_by' => $this->user2->id,
        'status' => ActionItemStatus::Open,
    ]);

    $response = $this->actingAs($this->user1)->get(route('action-items.dashboard'));

    $response->assertSuccessful();
    expect($response->viewData('actionItems'))->toHaveCount(1);
});

test('users cannot access other organizations action items directly', function () {
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org1->id,
        'created_by' => $this->user1->id,
    ]);

    ActionItem::factory()->create([
        'organization_id' => $this->org1->id,
        'minutes_of_meeting_id' => $meeting->id,
        'created_by' => $this->user1->id,
    ]);

    $this->actingAs($this->user2)
        ->get(route('meetings.action-items.index', $meeting))
        ->assertNotFound();
});

test('data created in one org does not leak to another', function () {
    $this->actingAs($this->user1)->post(route('meetings.store'), [
        'title' => 'Org1 Meeting',
        'meeting_date' => '2026-03-15',
        'prepared_by' => 'User 1',
    ])->assertRedirect();

    $this->actingAs($this->user2)->post(route('meetings.store'), [
        'title' => 'Org2 Meeting',
        'meeting_date' => '2026-03-15',
        'prepared_by' => 'User 2',
    ])->assertRedirect();

    $response1 = $this->actingAs($this->user1)->get(route('meetings.index'));
    $response1->assertSee('Org1 Meeting');
    $response1->assertDontSee('Org2 Meeting');

    $response2 = $this->actingAs($this->user2)->get(route('meetings.index'));
    $response2->assertSee('Org2 Meeting');
    $response2->assertDontSee('Org1 Meeting');
});
