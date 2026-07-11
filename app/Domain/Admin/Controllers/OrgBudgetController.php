<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\AiUsageLog;
use App\Domain\AI\Models\OrganizationAiBudget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class OrgBudgetController extends Controller
{
    public function index(): View
    {
        $organizations = Organization::query()->orderBy('name')->get();
        $budgets = OrganizationAiBudget::query()->get()->keyBy('organization_id');

        $todaySpend = AiUsageLog::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw('organization_id, SUM(cost) as total')
            ->groupBy('organization_id')
            ->pluck('total', 'organization_id');

        $monthSpend = AiUsageLog::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('organization_id, SUM(cost) as total')
            ->groupBy('organization_id')
            ->pluck('total', 'organization_id');

        return view('admin.ai.org-budgets', compact('organizations', 'budgets', 'todaySpend', 'monthSpend'));
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'daily_limit' => ['nullable', 'numeric', 'min:0'],
            'monthly_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        OrganizationAiBudget::query()->updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'daily_limit' => ($data['daily_limit'] ?? null) ?: null,
                'monthly_limit' => ($data['monthly_limit'] ?? null) ?: null,
            ],
        );

        return redirect()->route('admin.ai.org-budgets.index')
            ->with('success', __('AI budget updated for :name.', ['name' => $organization->name]));
    }
}
