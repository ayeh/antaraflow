<?php

declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Domain\Account\Exceptions\LimitExceededException;
use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\UsageTracking;

class SubscriptionService
{
    /** @var array<string, string> */
    private array $metricToField = [
        'meetings' => 'max_meetings_per_month',
        'audio_minutes' => 'max_audio_minutes_per_month',
        'storage_mb' => 'max_storage_mb',
        'members' => 'max_users',
    ];

    public function canPerform(Organization $org, string $metric): bool
    {
        $limit = $this->getPlanLimit($org, $metric);

        if ($limit === null) {
            return true;
        }

        return $this->getCurrentUsage($org, $metric) < $limit;
    }

    /** @throws LimitExceededException */
    public function checkLimit(Organization $org, string $metric): void
    {
        $limit = $this->getPlanLimit($org, $metric);

        if ($limit === null) {
            return;
        }

        $currentUsage = $this->getCurrentUsage($org, $metric);

        if ($currentUsage >= $limit) {
            $planName = $this->getActivePlanName($org);

            throw new LimitExceededException($metric, $currentUsage, $limit, $planName);
        }
    }

    public function getCurrentUsage(Organization $org, string $metric): int
    {
        $period = now()->format('Y-m');

        return (int) (UsageTracking::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('metric', $metric)
            ->where('period', $period)
            ->value('value') ?? 0);
    }

    public function getPlanLimit(Organization $org, string $metric): ?int
    {
        $field = $this->metricToField[$metric] ?? null;

        if (! $field) {
            return null;
        }

        $plan = $this->getActivePlan($org);

        if (! $plan) {
            return null;
        }

        $limit = $plan->subscriptionPlan->{$field} ?? null;

        if ($limit === null || $limit === -1) {
            return null;
        }

        return (int) $limit;
    }

    public function incrementUsage(Organization $org, string $metric, int $amount = 1): void
    {
        $period = now()->format('Y-m');

        UsageTracking::withoutGlobalScopes()->updateOrCreate(
            [
                'organization_id' => $org->id,
                'metric' => $metric,
                'period' => $period,
            ],
            []
        )->increment('value', $amount);
    }

    public function decrementUsage(Organization $org, string $metric, int $amount = 1): void
    {
        $period = now()->format('Y-m');

        $tracking = UsageTracking::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('metric', $metric)
            ->where('period', $period)
            ->first();

        if ($tracking && $tracking->value > 0) {
            $tracking->decrement('value', min($amount, (int) $tracking->value));
        }
    }

    public function resetMonthlyUsage(Organization $org): void
    {
        $period = now()->format('Y-m');

        UsageTracking::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('period', $period)
            ->whereIn('metric', ['meetings', 'audio_minutes'])
            ->update(['value' => 0]);
    }

    public function isFeatureEnabled(Organization $org, string $feature): bool
    {
        $plan = $this->getActivePlan($org);

        if (! $plan) {
            return false;
        }

        $features = $plan->subscriptionPlan->features ?? [];

        return (bool) ($features[$feature] ?? false);
    }

    private function getActivePlan(Organization $org): ?OrganizationSubscription
    {
        return OrganizationSubscription::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('status', 'active')
            ->with('subscriptionPlan')
            ->first();
    }

    private function getActivePlanName(Organization $org): string
    {
        return $this->getActivePlan($org)?->subscriptionPlan->name ?? 'Unknown';
    }
}
