<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Services\OrganizationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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

        return view('organizations.settings.edit', compact('organization'));
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageSettings', $organization);

        $validated = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        $this->organizationService->updateSettings($organization, $validated['settings']);

        return redirect()->route('organizations.settings.edit', $organization)
            ->with('success', 'Settings updated successfully.');
    }
}
