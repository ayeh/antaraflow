<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Webhook\Models\WebhookEndpoint;
use App\Models\User;
use App\Support\Enums\UserRole;
use App\Support\Enums\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Admin->value]);
});

test('admin can list webhook endpoints', function () {
    WebhookEndpoint::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'url' => 'https://example.com/webhook',
    ]);

    $response = $this->actingAs($this->user)->get(route('webhooks.index'));

    $response->assertSuccessful();
    $response->assertSee('https://example.com/webhook');
});

test('admin can create webhook endpoint', function () {
    $response = $this->actingAs($this->user)->post(route('webhooks.store'), [
        'url' => 'https://example.com/hook',
        'description' => 'Test webhook',
        'events' => [WebhookEvent::MeetingFinalized->value, WebhookEvent::MeetingApproved->value],
        'is_active' => true,
    ]);

    $response->assertRedirect(route('webhooks.index'));

    $this->assertDatabaseHas('webhook_endpoints', [
        'url' => 'https://example.com/hook',
        'organization_id' => $this->org->id,
    ]);

    $endpoint = WebhookEndpoint::query()->where('url', 'https://example.com/hook')->first();
    expect($endpoint->events)->toContain(WebhookEvent::MeetingFinalized->value)
        ->and($endpoint->secret)->not->toBeEmpty();
});

test('admin can update webhook endpoint', function () {
    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->put(route('webhooks.update', $endpoint), [
        'url' => 'https://updated.example.com/hook',
        'events' => [WebhookEvent::TranscriptionCompleted->value],
        'is_active' => false,
    ]);

    $response->assertRedirect(route('webhooks.index'));

    $endpoint->refresh();
    expect($endpoint->url)->toBe('https://updated.example.com/hook')
        ->and($endpoint->is_active)->toBeFalse();
});

test('admin can delete webhook endpoint', function () {
    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->delete(route('webhooks.destroy', $endpoint));

    $response->assertRedirect(route('webhooks.index'));
    $this->assertDatabaseMissing('webhook_endpoints', ['id' => $endpoint->id]);
});

test('admin can view delivery log', function () {
    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('webhooks.show', $endpoint));

    $response->assertSuccessful();
    $response->assertSee('Webhook Deliveries');
});

test('viewer cannot create webhook endpoint', function () {
    $viewer = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($viewer, ['role' => UserRole::Viewer->value]);

    $response = $this->actingAs($viewer)->post(route('webhooks.store'), [
        'url' => 'https://example.com/hook',
        'events' => [WebhookEvent::MeetingFinalized->value],
    ]);

    $response->assertForbidden();
});

test('store validation requires valid events', function () {
    $response = $this->actingAs($this->user)->post(route('webhooks.store'), [
        'url' => 'https://example.com/hook',
        'events' => ['invalid.event'],
    ]);

    $response->assertSessionHasErrors('events.0');
});

test('store validation requires at least one event', function () {
    $response = $this->actingAs($this->user)->post(route('webhooks.store'), [
        'url' => 'https://example.com/hook',
        'events' => [],
    ]);

    $response->assertSessionHasErrors('events');
});
