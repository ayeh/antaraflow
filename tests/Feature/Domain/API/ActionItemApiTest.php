<?php

declare(strict_types=1);

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
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
