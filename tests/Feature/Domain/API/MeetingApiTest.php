<?php

declare(strict_types=1);

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

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

it('GET /api/v1/meetings returns meetings for the organization', function () {
    MinutesOfMeeting::factory()->count(3)->create(['organization_id' => $this->org->id]);
    MinutesOfMeeting::factory()->create(); // different org

    $response = $this->getJson('/api/v1/meetings', $this->headers);

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('GET /api/v1/meetings/{id} returns the meeting', function () {
    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);

    $response = $this->getJson("/api/v1/meetings/{$meeting->id}", $this->headers);

    $response->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('id', $meeting->id)
            ->where('title', $meeting->title)
            ->where('status', $meeting->status->value)
            ->etc()
        );
});

it('GET /api/v1/meetings/{id} returns 404 for meeting in different org', function () {
    $otherMeeting = MinutesOfMeeting::factory()->create(); // different org

    $this->getJson("/api/v1/meetings/{$otherMeeting->id}", $this->headers)
        ->assertNotFound();
});

it('GET /api/v1/meetings requires auth', function () {
    $this->getJson('/api/v1/meetings')
        ->assertUnauthorized();
});

it('GET /api/v1/meetings response includes pagination meta', function () {
    MinutesOfMeeting::factory()->count(2)->create(['organization_id' => $this->org->id]);

    $response = $this->getJson('/api/v1/meetings', $this->headers);

    $response->assertOk()
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonStructure(['meta' => ['current_page', 'last_page', 'total']]);
});
