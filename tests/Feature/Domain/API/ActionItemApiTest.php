<?php

declare(strict_types=1);

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->orgOwner = User::factory()->create();
    $this->org->members()->attach($this->orgOwner->id, ['role' => 'owner']);

    $rawToken = 'test-api-key-'.uniqid();
    $this->apiKey = ApiKey::factory()->create([
        'organization_id' => $this->org->id,
        'secret_hash' => hash('sha256', $rawToken),
        'is_active' => true,
        'expires_at' => null,
    ]);
    $this->headers = ['Authorization' => 'Bearer '.$rawToken];
});

it('GET /api/v1/action-items returns action items for the organization', function () {
    ActionItem::factory()->count(3)->create(['organization_id' => $this->org->id]);
    ActionItem::factory()->create(); // different org

    $response = $this->getJson('/api/v1/action-items', $this->headers);

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('GET /api/v1/action-items can filter by meeting_id', function () {
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);
    ActionItem::factory()->count(2)->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $meeting->id,
    ]);
    ActionItem::factory()->create(['organization_id' => $this->org->id]); // different meeting

    $response = $this->getJson("/api/v1/action-items?meeting_id={$meeting->id}", $this->headers);

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('GET /api/v1/action-items can filter by status', function () {
    ActionItem::factory()->count(2)->completed()->create(['organization_id' => $this->org->id]);
    ActionItem::factory()->open()->create(['organization_id' => $this->org->id]);

    $response = $this->getJson('/api/v1/action-items?status=completed', $this->headers);

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.status', ActionItemStatus::Completed->value);
});

it('GET /api/v1/action-items does not return items from other organizations', function () {
    ActionItem::factory()->count(3)->create(); // different org
    ActionItem::factory()->create(['organization_id' => $this->org->id]);

    $response = $this->getJson('/api/v1/action-items', $this->headers);

    $response->assertOk()
        ->assertJsonCount(1, 'data');
});

it('GET /api/v1/action-items requires auth', function () {
    $this->getJson('/api/v1/action-items')
        ->assertUnauthorized();
});

it('GET /api/v1/action-items returns empty for meeting_id from different org', function () {
    $otherMeeting = MinutesOfMeeting::factory()->create(); // different org
    ActionItem::factory()->count(2)->create(['organization_id' => $this->org->id]);

    $response = $this->getJson("/api/v1/action-items?meeting_id={$otherMeeting->id}", $this->headers);

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});

it('GET /api/v1/action-items returns 422 for invalid status value', function () {
    $this->getJson('/api/v1/action-items?status=bogus', $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $msg) => str_contains($msg, 'Invalid status value'));
});

it('POST /api/v1/action-items creates an action item', function () {
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);

    $response = $this->postJson('/api/v1/action-items', [
        'title' => 'API Action Item',
        'minutes_of_meeting_id' => $meeting->id,
    ], $this->headers);

    $response->assertCreated()
        ->assertJsonPath('title', 'API Action Item')
        ->assertJsonPath('status', 'open');

    $this->assertDatabaseHas('action_items', [
        'title' => 'API Action Item',
        'organization_id' => $this->org->id,
    ]);
});

it('POST /api/v1/action-items validates required fields', function () {
    $this->postJson('/api/v1/action-items', [], $this->headers)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'minutes_of_meeting_id']);
});

it('POST /api/v1/action-items rejects meeting from different org', function () {
    $otherMeeting = MinutesOfMeeting::factory()->create(); // different org

    $this->postJson('/api/v1/action-items', [
        'title' => 'Hack',
        'minutes_of_meeting_id' => $otherMeeting->id,
    ], $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'Meeting not found'));
});

it('PATCH /api/v1/action-items/{id} updates an action item', function () {
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);
    $actionItem = ActionItem::factory()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $meeting->id,
    ]);

    $this->patchJson("/api/v1/action-items/{$actionItem->id}", [
        'title' => 'Updated',
        'status' => 'completed',
    ], $this->headers)
        ->assertOk()
        ->assertJsonPath('title', 'Updated')
        ->assertJsonPath('status', 'completed');
});

it('PATCH /api/v1/action-items/{id} returns 404 for wrong org', function () {
    $actionItem = ActionItem::factory()->create(); // different org

    $this->patchJson("/api/v1/action-items/{$actionItem->id}", [
        'title' => 'Hack',
    ], $this->headers)
        ->assertNotFound();
});
