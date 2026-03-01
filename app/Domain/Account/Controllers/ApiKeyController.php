<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Domain\Account\Requests\CreateApiKeyRequest;
use App\Domain\Account\Services\AuthorizationService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    public function __construct(private readonly AuthorizationService $authService) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $organization = Organization::findOrFail($user->current_organization_id);

        abort_unless(
            $this->authService->hasPermission($user, $organization, 'manage_organization'),
            403,
            'Only admins and owners can manage API keys.'
        );

        $apiKeys = ApiKey::query()
            ->where('organization_id', $organization->id)
            ->latest()
            ->get();

        return view('api-keys.index', compact('apiKeys'));
    }

    public function store(CreateApiKeyRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $organization = Organization::findOrFail($user->current_organization_id);

        abort_unless(
            $this->authService->hasPermission($user, $organization, 'manage_organization'),
            403
        );

        $validated = $request->validated();

        $rawKey = Str::random(32);
        $prefix = Str::random(8);
        $fullKey = "af_{$prefix}_{$rawKey}";

        ApiKey::query()->create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'key' => $prefix,
            'secret_hash' => hash('sha256', $fullKey),
            'permissions' => $validated['permissions'],
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('api-keys.index')
            ->with('api_key_created', $fullKey)
            ->with('success', 'API key created. Copy it now — it will not be shown again.');
    }

    public function destroy(Request $request, ApiKey $apiKey): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $organization = Organization::findOrFail($user->current_organization_id);

        abort_unless(
            $this->authService->hasPermission($user, $organization, 'manage_organization'),
            403
        );

        abort_unless($apiKey->organization_id === $organization->id, 403);

        $apiKey->delete();

        return redirect()->route('api-keys.index')
            ->with('success', 'API key revoked successfully.');
    }
}
