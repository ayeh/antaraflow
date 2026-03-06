<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Services;

use App\Domain\Webhook\Jobs\DispatchWebhookJob;
use App\Domain\Webhook\Models\WebhookEndpoint;

class WebhookDispatcher
{
    public function dispatch(int $organizationId, string $event, array $payload): void
    {
        $endpoints = WebhookEndpoint::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();

        foreach ($endpoints as $endpoint) {
            if ($endpoint->subscribesToEvent($event)) {
                DispatchWebhookJob::dispatch($endpoint, $event, $payload);
            }
        }
    }
}
