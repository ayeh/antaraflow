<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\SubscriptionPlan;
use App\Domain\Admin\Models\SmtpConfiguration;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $query = Organization::query()
            ->with(['members', 'subscriptions.subscriptionPlan'])
            ->withCount(['members', 'subscriptions']);

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->input('status') === 'suspended') {
            $query->where('is_suspended', true);
        } elseif ($request->input('status') === 'active') {
            $query->where(function ($q) {
                $q->where('is_suspended', false)->orWhereNull('is_suspended');
            });
        }

        if ($planId = $request->input('plan')) {
            $query->whereHas('subscriptions', function ($q) use ($planId) {
                $q->where('subscription_plan_id', $planId)
                    ->where('status', 'active');
            });
        }

        $organizations = $query->latest()->paginate(20)->withQueryString();

        $plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('admin.organizations.index', compact('organizations', 'plans'));
    }

    public function show(Organization $organization): View
    {
        $organization->load(['members', 'subscriptions.subscriptionPlan']);

        $owner = $organization->members->firstWhere('pivot.role', 'owner');

        $activeSubscription = $organization->subscriptions
            ->where('status', 'active')
            ->first();

        $totalMeetings = MinutesOfMeeting::query()
            ->where('organization_id', $organization->id)
            ->count();

        $meetingsLast30Days = MinutesOfMeeting::query()
            ->where('organization_id', $organization->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $storageUsedMb = 0;

        $hasSmtpConfig = SmtpConfiguration::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->exists();

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('admin.organizations.show', compact(
            'organization',
            'owner',
            'activeSubscription',
            'totalMeetings',
            'meetingsLast30Days',
            'storageUsedMb',
            'hasSmtpConfig',
            'plans',
        ));
    }

    public function suspend(Request $request, Organization $organization): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $organization->update([
            'is_suspended' => true,
            'suspended_at' => now(),
            'suspended_reason' => $request->input('reason'),
        ]);

        return redirect()->route('admin.organizations.show', $organization)
            ->with('success', "Organization \"{$organization->name}\" has been suspended.");
    }

    public function unsuspend(Organization $organization): RedirectResponse
    {
        $organization->update([
            'is_suspended' => false,
            'suspended_at' => null,
            'suspended_reason' => null,
        ]);

        return redirect()->route('admin.organizations.show', $organization)
            ->with('success', "Organization \"{$organization->name}\" has been unsuspended.");
    }

    public function changePlan(Request $request, Organization $organization): RedirectResponse
    {
        $request->validate([
            'plan_id' => ['required', 'exists:subscription_plans,id'],
        ]);

        $subscription = $organization->subscriptions()
            ->where('status', 'active')
            ->first();

        if ($subscription) {
            $subscription->update([
                'subscription_plan_id' => $request->input('plan_id'),
            ]);
        } else {
            $organization->subscriptions()->create([
                'subscription_plan_id' => $request->input('plan_id'),
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addYear(),
            ]);
        }

        return redirect()->route('admin.organizations.show', $organization)
            ->with('success', 'Subscription plan has been updated.');
    }
}
