<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\UsageTracking;
use App\Domain\Account\Services\AuthorizationService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class UsageController extends Controller
{
    public function __construct(private readonly AuthorizationService $authService) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $organization = Organization::findOrFail($user->current_organization_id);

        abort_unless(
            $this->authService->hasPermission($user, $organization, 'manage_organization'),
            403
        );

        $currentPeriod = now()->format('Y-m');

        $usage = UsageTracking::query()
            ->where('organization_id', $organization->id)
            ->where('period', $currentPeriod)
            ->get()
            ->keyBy('metric');

        $history = UsageTracking::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('period')
            ->limit(12)
            ->get()
            ->groupBy('metric');

        $subscription = OrganizationSubscription::query()
            ->with('subscriptionPlan')
            ->where('organization_id', $organization->id)
            ->latest()
            ->first();

        return view('usage.index', compact('usage', 'history', 'subscription', 'currentPeriod'));
    }
}
