<?php

declare(strict_types=1);

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
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

it('API info endpoint is public', function (): void {
    $this->getJson('/api/v1')
        ->assertOk()
        ->assertJsonPath('version', 'v1');
});

it('authenticated endpoints require valid API key', function (): void {
    $this->getJson('/api/v1/analytics/summary')
        ->assertUnauthorized();
});

it('valid API key grants access to analytics', function (): void {
    $this->getJson('/api/v1/analytics/summary', $this->headers)
        ->assertOk()
        ->assertJsonStructure(['data' => ['total_meetings', 'total_action_items', 'completed_action_items']]);
});

it('valid API key grants access to meetings', function (): void {
    $this->getJson('/api/v1/meetings', $this->headers)
        ->assertOk();
});

it('valid API key grants access to webhooks', function (): void {
    $this->getJson('/api/v1/webhooks', $this->headers)
        ->assertOk();
});

it('invalid API key is rejected', function (): void {
    $this->getJson('/api/v1/meetings', ['Authorization' => 'Bearer invalid-token'])
        ->assertUnauthorized();
});
