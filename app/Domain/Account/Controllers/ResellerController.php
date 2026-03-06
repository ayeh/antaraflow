<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Requests\StoreSubOrganizationRequest;
use App\Domain\Account\Services\ResellerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ResellerController extends Controller
{
    public function __construct(
        private ResellerService $resellerService,
    ) {}

    public function dashboard(): View
    {
        $organization = auth()->user()->currentOrganization;

        abort_unless($organization->isReseller(), 403);

        $usageSummary = $this->resellerService->getUsageSummary($organization);
        $commission = $this->resellerService->calculateCommission($organization);
        $resellerSetting = $organization->resellerSetting;

        return view('reseller.dashboard', compact(
            'organization',
            'usageSummary',
            'commission',
            'resellerSetting',
        ));
    }

    public function subOrganizations(): View
    {
        $organization = auth()->user()->currentOrganization;

        abort_unless($organization->isReseller(), 403);

        $subOrganizations = $this->resellerService->getSubOrganizations($organization);
        $resellerSetting = $organization->resellerSetting;

        return view('reseller.sub-organizations', compact(
            'organization',
            'subOrganizations',
            'resellerSetting',
        ));
    }

    public function createSubOrg(): View
    {
        $organization = auth()->user()->currentOrganization;

        abort_unless($organization->isReseller(), 403);

        $resellerSetting = $organization->resellerSetting;

        return view('reseller.sub-organizations', [
            'organization' => $organization,
            'subOrganizations' => $this->resellerService->getSubOrganizations($organization),
            'resellerSetting' => $resellerSetting,
            'showCreateForm' => true,
        ]);
    }

    public function storeSubOrg(StoreSubOrganizationRequest $request): RedirectResponse
    {
        $organization = auth()->user()->currentOrganization;

        abort_unless($organization->isReseller(), 403);

        try {
            $this->resellerService->createSubOrganization($organization, $request->validated());

            return redirect()->route('reseller.sub-organizations')
                ->with('success', 'Sub-organization created successfully.');
        } catch (\RuntimeException $e) {
            return redirect()->route('reseller.sub-organizations')
                ->with('error', $e->getMessage());
        }
    }
}
