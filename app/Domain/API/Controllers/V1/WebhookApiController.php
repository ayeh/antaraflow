<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\API\Controllers\ApiController;
use App\Domain\API\Requests\V1\StoreApiWebhookRequest;
use App\Domain\Webhook\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $webhooks = WebhookEndpoint::query()
            ->where('organization_id', $this->organizationId($request))
            ->get();

        return response()->json(['data' => $webhooks]);
    }

    public function store(StoreApiWebhookRequest $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $webhook = WebhookEndpoint::query()->create([
            'organization_id' => $orgId,
            'url' => $request->validated('url'),
            'events' => $request->validated('events'),
            'description' => $request->validated('description'),
            'secret' => Str::random(32),
            'is_active' => true,
            'created_by' => $this->resolveCreatedBy($orgId),
        ]);

        return response()->json(['data' => $webhook], 201);
    }

    public function destroy(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        abort_unless($webhookEndpoint->organization_id === $this->organizationId($request), 404);

        $webhookEndpoint->delete();

        return response()->json(['message' => 'Webhook deleted.']);
    }
}
