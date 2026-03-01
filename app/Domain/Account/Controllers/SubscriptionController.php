<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $orgId = $user->current_organization_id;

        $currentSubscription = OrganizationSubscription::query()
            ->with('subscriptionPlan')
            ->where('organization_id', $orgId)
            ->latest()
            ->first();

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('subscription.index', compact('currentSubscription', 'plans'));
    }
}
