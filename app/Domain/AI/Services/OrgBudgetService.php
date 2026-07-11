<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\AI\Models\AiUsageLog;
use App\Domain\AI\Models\OrganizationAiBudget;
use App\Infrastructure\AI\Exceptions\OrgBudgetExceededException;

/**
 * Per-organization AI spend limits. Enforced at call time (block the org once
 * its daily or monthly limit is reached) and used for graduated alerting.
 */
class OrgBudgetService
{
    public function budgetFor(int $organizationId): ?OrganizationAiBudget
    {
        return OrganizationAiBudget::query()
            ->where('organization_id', $organizationId)
            ->first();
    }

    public function dailySpend(int $organizationId): float
    {
        return (float) AiUsageLog::query()
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('cost');
    }

    public function monthSpend(int $organizationId): float
    {
        return (float) AiUsageLog::query()
            ->where('organization_id', $organizationId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('cost');
    }

    /**
     * True when the org has a limit and has met or exceeded it.
     */
    public function isOverLimit(int $organizationId): bool
    {
        $budget = $this->budgetFor($organizationId);

        if (! $budget) {
            return false;
        }

        if ($budget->daily_limit && $this->dailySpend($organizationId) >= $budget->daily_limit) {
            return true;
        }

        if ($budget->monthly_limit && $this->monthSpend($organizationId) >= $budget->monthly_limit) {
            return true;
        }

        return false;
    }

    /**
     * The highest utilisation ratio (0..1+) across the org's daily and monthly
     * limits, for graduated alerting. Null when the org has no limit set.
     */
    public function utilisation(int $organizationId): ?float
    {
        $budget = $this->budgetFor($organizationId);

        if (! $budget) {
            return null;
        }

        $ratios = [];

        if ($budget->daily_limit > 0) {
            $ratios[] = $this->dailySpend($organizationId) / $budget->daily_limit;
        }

        if ($budget->monthly_limit > 0) {
            $ratios[] = $this->monthSpend($organizationId) / $budget->monthly_limit;
        }

        return $ratios === [] ? null : max($ratios);
    }

    /**
     * Throw when the org is over budget. No-op when org is null or under limit.
     */
    public function guard(?int $organizationId): void
    {
        if ($organizationId !== null && $this->isOverLimit($organizationId)) {
            throw OrgBudgetExceededException::make();
        }
    }
}
