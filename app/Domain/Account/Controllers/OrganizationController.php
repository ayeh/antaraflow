<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Requests\CreateOrganizationRequest;
use App\Domain\Account\Requests\UpdateOrganizationRequest;
use App\Domain\Account\Services\OrganizationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private OrganizationService $organizationService,
    ) {}

    public function index(Request $request): View
    {
        $organizations = $request->user()->organizations;

        return view('organizations.index', compact('organizations'));
    }

    public function create(): View
    {
        return view('organizations.create');
    }

    public function store(CreateOrganizationRequest $request): RedirectResponse
    {
        $organization = $this->organizationService->createOrganization(
            $request->user(),
            $request->validated('name'),
            $request->validated('slug'),
            $request->validated('description'),
        );

        return redirect()->route('organizations.show', $organization)
            ->with('success', 'Organization created successfully.');
    }

    public function show(Organization $organization): View
    {
        $this->authorize('view', $organization);

        $members = $organization->members()->withPivot('role')->get();
        $subscription = $organization->subscriptions()->with('subscriptionPlan')->latest()->first();

        return view('organizations.show', compact('organization', 'members', 'subscription'));
    }

    public function edit(Organization $organization): View
    {
        $this->authorize('update', $organization);

        return view('organizations.edit', compact('organization'));
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);

        $organization->update($request->validated());

        return redirect()->route('organizations.show', $organization)
            ->with('success', 'Organization updated successfully.');
    }

    public function destroy(Organization $organization): RedirectResponse
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return redirect()->route('organizations.index')
            ->with('success', 'Organization deleted successfully.');
    }
}
