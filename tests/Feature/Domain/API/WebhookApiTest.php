<?php

declare(strict_types=1);

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Domain\Webhook\Models\WebhookEndpoint;
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

it('can list webhook endpoints', function (): void {
    WebhookEndpoint::factory()->count(2)->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->orgOwner->id,
    ]);

    $this->getJson('/api/v1/webhooks', $this->headers)
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('can create a webhook endpoint', function (): void {
    $this->postJson('/api/v1/webhooks', [
        'url' => 'https://example.com/webhook',
        'events' => ['meeting.created', 'meeting.finalized'],
    ], $this->headers)
        ->assertCreated()
        ->assertJsonPath('data.url', 'https://example.com/webhook');
});

it('requires url and events when creating webhook', function (): void {
    $this->postJson('/api/v1/webhooks', [], $this->headers)
        ->assertUnprocessable();
});

it('can delete a webhook endpoint', function (): void {
    $webhook = WebhookEndpoint::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->orgOwner->id,
    ]);

    $this->deleteJson("/api/v1/webhooks/{$webhook->id}", [], $this->headers)
        ->assertOk();

    expect(WebhookEndpoint::find($webhook->id))->toBeNull();
});

it('cannot delete a webhook from another organization', function (): void {
    $otherWebhook = WebhookEndpoint::factory()->create();

    $this->deleteJson("/api/v1/webhooks/{$otherWebhook->id}", [], $this->headers)
        ->assertNotFound();
});

it('GET /api/v1/webhooks requires authentication', function (): void {
    $this->getJson('/api/v1/webhooks')
        ->assertUnauthorized();
});
