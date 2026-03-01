<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Requests\UpdateOrganizationSettingsRequest;
use App\Domain\Account\Services\OrganizationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OrganizationSettingsController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private OrganizationService $organizationService,
    ) {}

    public function edit(Organization $organization): View
    {
        $this->authorize('manageSettings', $organization);

        $members = $organization->members()->withPivot('role')->get();
        $subscription = $organization->subscriptions()->with('subscriptionPlan')->latest()->first();

        return view('organizations.settings.edit', compact('organization', 'members', 'subscription'));
    }

    public function update(UpdateOrganizationSettingsRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageSettings', $organization);

        $data = $request->validated();

        $organization->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'timezone' => $data['timezone'],
            'language' => $data['language'],
        ]);

        if (isset($data['settings'])) {
            $this->organizationService->updateSettings($organization, $data['settings']);
        }

        return redirect()->route('organizations.settings.edit', $organization)
            ->with('success', 'Settings updated successfully.');
    }

    public function uploadLogo(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageSettings', $organization);

        $request->validate([
            'logo' => ['required', 'image', 'max:2048'],
        ]);

        if ($organization->logo_path) {
            Storage::disk('public')->delete($organization->logo_path);
        }

        $path = $request->file('logo')->store('logos', 'public');
        $organization->update(['logo_path' => $path]);

        return redirect()->route('organizations.settings.edit', $organization)
            ->with('success', 'Logo updated successfully.');
    }
}
