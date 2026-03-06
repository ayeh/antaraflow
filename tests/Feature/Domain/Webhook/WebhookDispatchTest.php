<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Webhook\Jobs\DispatchWebhookJob;
use App\Domain\Webhook\Models\WebhookEndpoint;
use App\Domain\Webhook\Services\WebhookDispatcher;
use App\Models\User;
use App\Support\Enums\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
});

test('dispatcher dispatches jobs to matching endpoints', function () {
    Queue::fake();

    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'events' => [WebhookEvent::MeetingFinalized->value],
        'is_active' => true,
    ]);

    $nonMatchingEndpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'events' => [WebhookEvent::TranscriptionCompleted->value],
        'is_active' => true,
    ]);

    $dispatcher = app(WebhookDispatcher::class);
    $dispatcher->dispatch($this->org->id, WebhookEvent::MeetingFinalized->value, ['meeting_id' => 1]);

    Queue::assertPushed(DispatchWebhookJob::class, 1);
    Queue::assertPushed(DispatchWebhookJob::class, function ($job) use ($endpoint) {
        return $job->endpoint->id === $endpoint->id
            && $job->event === WebhookEvent::MeetingFinalized->value;
    });
});

test('dispatcher skips inactive endpoints', function () {
    Queue::fake();

    WebhookEndpoint::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'events' => [WebhookEvent::MeetingFinalized->value],
        'is_active' => false,
    ]);

    $dispatcher = app(WebhookDispatcher::class);
    $dispatcher->dispatch($this->org->id, WebhookEvent::MeetingFinalized->value, ['meeting_id' => 1]);

    Queue::assertNothingPushed();
});

test('webhook job sends HTTP request with signature', function () {
    Http::fake(['*' => Http::response('OK', 200)]);

    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'secret' => 'test-secret-key',
    ]);

    $job = new DispatchWebhookJob($endpoint, 'meeting.finalized', ['meeting_id' => 42]);
    $job->handle();

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Signature-256')
            && $request->hasHeader('X-Webhook-Event')
            && $request->header('X-Webhook-Event')[0] === 'meeting.finalized';
    });

    $this->assertDatabaseHas('webhook_deliveries', [
        'webhook_endpoint_id' => $endpoint->id,
        'event' => 'meeting.finalized',
        'successful' => true,
        'response_status' => 200,
    ]);
});

test('webhook job records failure on non-2xx response', function () {
    Http::fake(['*' => Http::response('Error', 500)]);

    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'failure_count' => 0,
    ]);

    $job = new DispatchWebhookJob($endpoint, 'meeting.finalized', ['meeting_id' => 1]);
    $job->handle();

    $endpoint->refresh();
    expect($endpoint->failure_count)->toBe(1);

    $this->assertDatabaseHas('webhook_deliveries', [
        'webhook_endpoint_id' => $endpoint->id,
        'successful' => false,
        'response_status' => 500,
    ]);
});

test('webhook endpoint auto-disables after 50 consecutive failures', function () {
    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'failure_count' => 49,
        'is_active' => true,
    ]);

    $endpoint->recordFailure();
    $endpoint->refresh();

    expect($endpoint->failure_count)->toBe(50)
        ->and($endpoint->is_active)->toBeFalse();
});

test('webhook endpoint resets failures on success', function () {
    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'failure_count' => 10,
    ]);

    $endpoint->resetFailures();
    $endpoint->refresh();

    expect($endpoint->failure_count)->toBe(0);
});

test('endpoint subscribesToEvent works correctly', function () {
    $endpoint = WebhookEndpoint::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'events' => [WebhookEvent::MeetingFinalized->value, WebhookEvent::MeetingApproved->value],
    ]);

    expect($endpoint->subscribesToEvent(WebhookEvent::MeetingFinalized->value))->toBeTrue()
        ->and($endpoint->subscribesToEvent(WebhookEvent::TranscriptionCompleted->value))->toBeFalse();
});
