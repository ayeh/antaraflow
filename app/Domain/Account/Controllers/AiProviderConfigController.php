<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Account\Models\Organization;
use App\Domain\Account\Requests\CreateAiProviderConfigRequest;
use App\Domain\Account\Requests\UpdateAiProviderConfigRequest;
use App\Domain\Account\Services\AuthorizationService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class AiProviderConfigController extends Controller
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
            'Only admins and owners can manage AI provider configurations.'
        );

        $configs = AiProviderConfig::query()->orderBy('provider')->get();

        return view('ai-provider-configs.index', compact('configs'));
    }

    public function create(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $organization = Organization::findOrFail($user->current_organization_id);

        abort_unless(
            $this->authService->hasPermission($user, $organization, 'manage_organization'),
            403
        );

        return view('ai-provider-configs.create');
    }

    public function store(CreateAiProviderConfigRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $organization = Organization::findOrFail($user->current_organization_id);

        abort_unless(
            $this->authService->hasPermission($user, $organization, 'manage_organization'),
            403
        );

        $validated = $request->validated();

        if ($request->boolean('is_default')) {
            AiProviderConfig::query()->update(['is_default' => false]);
        }

        AiProviderConfig::query()->create([
            'organization_id' => $organization->id,
            'provider' => $validated['provider'],
            'display_name' => $validated['display_name'],
            'api_key_encrypted' => isset($validated['api_key']) && $validated['api_key'] !== null
                ? Crypt::encryptString($validated['api_key'])
                : null,
            'model' => $validated['model'],
            'base_url' => $validated['base_url'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'settings' => $validated['settings'] ?? null,
        ]);

        return redirect()->route('ai-provider-configs.index')
            ->with('success', 'AI provider configuration created successfully.');
    }

    public function edit(Request $request, AiProviderConfig $aiProviderConfig): View
    {
        /** @var User $user */
        $user = $request->user();
        $organization = Organization::findOrFail($user->current_organization_id);

        abort_unless(
            $this->authService->hasPermission($user, $organization, 'manage_organization'),
            403
        );

        return view('ai-provider-configs.edit', compact('aiProviderConfig'));
    }

    public function update(UpdateAiProviderConfigRequest $request, AiProviderConfig $aiProviderConfig): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $organization = Organization::findOrFail($user->current_organization_id);

        abort_unless(
            $this->authService->hasPermission($user, $organization, 'manage_organization'),
            403
        );

        $validated = $request->validated();

        if ($request->boolean('is_default')) {
            AiProviderConfig::query()
                ->whereKeyNot($aiProviderConfig->id)
                ->update(['is_default' => false]);
        }

        $aiProviderConfig->update([
            'provider' => $validated['provider'],
            'display_name' => $validated['display_name'],
            'api_key_encrypted' => isset($validated['api_key']) && $validated['api_key'] !== null
                ? Crypt::encryptString($validated['api_key'])
                : $aiProviderConfig->api_key_encrypted,
            'model' => $validated['model'],
            'base_url' => $validated['base_url'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
            'settings' => $validated['settings'] ?? null,
        ]);

        return redirect()->route('ai-provider-configs.index')
            ->with('success', 'AI provider configuration updated successfully.');
    }

    public function destroy(Request $request, AiProviderConfig $aiProviderConfig): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $organization = Organization::findOrFail($user->current_organization_id);

        abort_unless(
            $this->authService->hasPermission($user, $organization, 'manage_organization'),
            403
        );

        $aiProviderConfig->delete();

        return redirect()->route('ai-provider-configs.index')
            ->with('success', 'AI provider configuration deleted successfully.');
    }
}
