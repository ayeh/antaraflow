<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Controllers;

use App\Domain\Webhook\Jobs\DispatchWebhookJob;
use App\Domain\Webhook\Models\WebhookEndpoint;
use App\Domain\Webhook\Requests\CreateWebhookEndpointRequest;
use App\Domain\Webhook\Requests\UpdateWebhookEndpointRequest;
use App\Support\Enums\WebhookEvent;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebhookEndpointController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', WebhookEndpoint::class);

        $endpoints = WebhookEndpoint::query()
            ->withCount('deliveries')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('webhooks.index', compact('endpoints'));
    }

    public function create(): View
    {
        $this->authorize('create', WebhookEndpoint::class);

        $webhookEvents = WebhookEvent::cases();

        return view('webhooks.create', compact('webhookEvents'));
    }

    public function store(CreateWebhookEndpointRequest $request): RedirectResponse
    {
        $this->authorize('create', WebhookEndpoint::class);

        $data = $request->validated();
        $data['organization_id'] = $request->user()->current_organization_id;
        $data['created_by'] = $request->user()->id;
        $data['secret'] = Str::random(32);
        $data['is_active'] = $request->boolean('is_active', true);

        WebhookEndpoint::query()->create($data);

        return redirect()->route('webhooks.index')
            ->with('success', 'Webhook endpoint created successfully.');
    }

    public function show(WebhookEndpoint $webhook): View
    {
        $this->authorize('view', $webhook);

        $deliveries = $webhook->deliveries()
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return view('webhooks.show', compact('webhook', 'deliveries'));
    }

    public function edit(WebhookEndpoint $webhook): View
    {
        $this->authorize('update', $webhook);

        $webhookEvents = WebhookEvent::cases();

        return view('webhooks.edit', compact('webhook', 'webhookEvents'));
    }

    public function update(UpdateWebhookEndpointRequest $request, WebhookEndpoint $webhook): RedirectResponse
    {
        $this->authorize('update', $webhook);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $webhook->update($data);

        return redirect()->route('webhooks.index')
            ->with('success', 'Webhook endpoint updated successfully.');
    }

    public function destroy(WebhookEndpoint $webhook): RedirectResponse
    {
        $this->authorize('delete', $webhook);

        $webhook->delete();

        return redirect()->route('webhooks.index')
            ->with('success', 'Webhook endpoint deleted.');
    }

    public function ping(WebhookEndpoint $webhook): RedirectResponse
    {
        $this->authorize('update', $webhook);

        DispatchWebhookJob::dispatch($webhook, 'ping', [
            'message' => 'Test ping from '.config('app.name'),
            'timestamp' => now()->toIso8601String(),
        ]);

        return redirect()->route('webhooks.show', $webhook)
            ->with('success', 'Test ping sent.');
    }
}
