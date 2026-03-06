<?php

declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ResellerService
{
    public function __construct(
        private AuditService $auditService,
    ) {}

    /** @return Collection<int, Organization> */
    public function getSubOrganizations(Organization $reseller): Collection
    {
        return Organization::query()
            ->where('parent_organization_id', $reseller->id)
            ->with(['members', 'subscriptions.subscriptionPlan'])
            ->get();
    }

    /**
     * @param  array{name: string, slug?: string, description?: string, owner_email: string, owner_name: string}  $data
     */
    public function createSubOrganization(Organization $reseller, array $data): Organization
    {
        $resellerSetting = $reseller->resellerSetting;

        if ($resellerSetting?->max_sub_organizations !== null) {
            $currentCount = Organization::query()
                ->where('parent_organization_id', $reseller->id)
                ->count();

            if ($currentCount >= $resellerSetting->max_sub_organizations) {
                throw new \RuntimeException('Maximum sub-organization limit reached.');
            }
        }

        $organization = Organization::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']).'-'.Str::random(5),
            'description' => $data['description'] ?? null,
            'parent_organization_id' => $reseller->id,
        ]);

        $owner = User::query()->where('email', $data['owner_email'])->first();

        if (! $owner) {
            $owner = User::query()->create([
                'name' => $data['owner_name'],
                'email' => $data['owner_email'],
                'password' => bcrypt(Str::random(32)),
            ]);
        }

        $organization->members()->attach($owner, ['role' => UserRole::Owner->value]);
        $owner->update(['current_organization_id' => $organization->id]);

        $freePlan = \App\Domain\Account\Models\SubscriptionPlan::query()
            ->where('slug', 'free')
            ->first();

        if ($freePlan) {
            OrganizationSubscription::withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'subscription_plan_id' => $freePlan->id,
                'status' => 'active',
                'starts_at' => now(),
            ]);
        }

        $this->auditService->log('sub_organization_created', $reseller, null, [
            'sub_organization_id' => $organization->id,
            'sub_organization_name' => $organization->name,
        ]);

        return $organization;
    }

    /**
     * @return array{total_sub_orgs: int, total_users: int, total_meetings: int}
     */
    public function getUsageSummary(Organization $reseller): array
    {
        $subOrgIds = Organization::query()
            ->where('parent_organization_id', $reseller->id)
            ->pluck('id');

        $totalUsers = \DB::table('organization_user')
            ->whereIn('organization_id', $subOrgIds)
            ->distinct('user_id')
            ->count('user_id');

        $totalMeetings = \DB::table('minutes_of_meetings')
            ->whereIn('organization_id', $subOrgIds)
            ->count();

        return [
            'total_sub_orgs' => $subOrgIds->count(),
            'total_users' => $totalUsers,
            'total_meetings' => $totalMeetings,
        ];
    }

    public function calculateCommission(Organization $reseller, string $period = 'current_month'): float
    {
        $resellerSetting = $reseller->resellerSetting;

        if (! $resellerSetting || $resellerSetting->commission_rate <= 0) {
            return 0.0;
        }

        $subOrgIds = Organization::query()
            ->where('parent_organization_id', $reseller->id)
            ->pluck('id');

        $query = OrganizationSubscription::withoutGlobalScopes()
            ->whereIn('organization_id', $subOrgIds)
            ->where('status', 'active')
            ->with('subscriptionPlan');

        if ($period === 'current_month') {
            $query->where('starts_at', '>=', now()->startOfMonth());
        }

        $totalRevenue = $query->get()->sum(fn ($sub) => (float) ($sub->subscriptionPlan->price ?? 0));

        return round($totalRevenue * ($resellerSetting->commission_rate / 100), 2);
    }

    public function isResellerOrganization(Organization $organization): bool
    {
        return $organization->resellerSetting?->is_reseller === true;
    }
}
