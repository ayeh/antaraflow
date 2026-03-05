<?php

declare(strict_types=1);

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Models\User;
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

it('includes rate limit headers on API responses', function () {
    $response = $this->getJson('/api/v1/meetings', $this->headers);

    $response->assertOk();
    $response->assertHeader('X-RateLimit-Limit', '60');
    $response->assertHeader('X-RateLimit-Remaining');
});

it('returns 429 when rate limit is exceeded', function () {
    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/v1/meetings', $this->headers)->assertOk();
    }

    $response = $this->getJson('/api/v1/meetings', $this->headers);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
});

it('rate limits per API key independently', function () {
    $rawTokenA = 'key-a-'.uniqid();
    $rawTokenB = 'key-b-'.uniqid();

    ApiKey::factory()->create([
        'organization_id' => $this->org->id,
        'secret_hash' => hash('sha256', $rawTokenA),
        'is_active' => true,
        'expires_at' => null,
    ]);

    ApiKey::factory()->create([
        'organization_id' => $this->org->id,
        'secret_hash' => hash('sha256', $rawTokenB),
        'is_active' => true,
        'expires_at' => null,
    ]);

    $headersA = ['Authorization' => 'Bearer '.$rawTokenA];
    $headersB = ['Authorization' => 'Bearer '.$rawTokenB];

    // Exhaust key A's limit
    for ($i = 0; $i < 60; $i++) {
        $this->getJson('/api/v1/meetings', $headersA);
    }

    // Key A should be rate limited
    $this->getJson('/api/v1/meetings', $headersA)->assertStatus(429);

    // Key B should still work
    $this->getJson('/api/v1/meetings', $headersB)->assertOk();
});

it('decrements remaining count with each request', function () {
    $first = $this->getJson('/api/v1/meetings', $this->headers);
    $first->assertHeader('X-RateLimit-Remaining', '59');

    $second = $this->getJson('/api/v1/meetings', $this->headers);
    $second->assertHeader('X-RateLimit-Remaining', '58');
});
