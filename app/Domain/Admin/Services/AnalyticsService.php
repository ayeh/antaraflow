<?php

declare(strict_types=1);

namespace App\Domain\Admin\Services;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    private const int CACHE_TTL = 900; // 15 minutes

    /** @return array<string, mixed> */
    public function getStatCards(): array
    {
        return Cache::remember('analytics.stat_cards', self::CACHE_TTL, function () {
            $activeSubs = OrganizationSubscription::query()
                ->where('status', 'active')
                ->with('subscriptionPlan')
                ->get();

            $mrr = $activeSubs->sum(fn ($sub) => (float) $sub->subscriptionPlan->price_monthly);

            return [
                'total_users' => User::query()->count(),
                'total_organizations' => Organization::query()->count(),
                'total_meetings' => MinutesOfMeeting::query()->count(),
                'active_subscriptions' => $activeSubs->count(),
                'mrr' => $mrr,
            ];
        });
    }

    /** @return array<string, int> */
    public function getUserGrowth(string $period = 'daily'): array
    {
        return Cache::remember("analytics.user_growth.{$period}", self::CACHE_TTL, function () use ($period) {
            return $this->getGrowthData(User::class, $period);
        });
    }

    /** @return array<string, int> */
    public function getOrgGrowth(string $period = 'daily'): array
    {
        return Cache::remember("analytics.org_growth.{$period}", self::CACHE_TTL, function () use ($period) {
            return $this->getGrowthData(Organization::class, $period);
        });
    }

    /** @return array<string, int> */
    public function getSubscriptionDistribution(): array
    {
        return Cache::remember('analytics.subscription_distribution', self::CACHE_TTL, function () {
            return OrganizationSubscription::query()
                ->where('organization_subscriptions.status', 'active')
                ->join('subscription_plans', 'organization_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
                ->selectRaw('subscription_plans.name, COUNT(*) as count')
                ->groupBy('subscription_plans.name')
                ->pluck('count', 'name')
                ->toArray();
        });
    }

    /** @return Collection<int, User> */
    public function getRecentRegistrations(int $limit = 10): Collection
    {
        return User::query()
            ->with('organizations')
            ->latest()
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, Organization> */
    public function getTopOrganizations(int $limit = 10): Collection
    {
        return Organization::query()
            ->withCount('members')
            ->orderByDesc('members_count')
            ->limit($limit)
            ->get();
    }

    /** @return array<string, int> */
    public function getActivityHeatmap(): array
    {
        return Cache::remember('analytics.activity_heatmap', self::CACHE_TTL, function () {
            $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $result = array_fill_keys($dayNames, 0);

            $driver = DB::getDriverName();

            $query = MinutesOfMeeting::query()
                ->where('created_at', '>=', now()->subDays(90));

            if ($driver === 'sqlite') {
                $data = $query
                    ->selectRaw("CAST(strftime('%w', created_at) AS INTEGER) as day_num, COUNT(*) as count")
                    ->groupBy('day_num')
                    ->pluck('count', 'day_num');

                foreach ($data as $dayNum => $count) {
                    $result[$dayNames[$dayNum]] = $count;
                }
            } else {
                $data = $query
                    ->selectRaw('DAYOFWEEK(created_at) as day_num, COUNT(*) as count')
                    ->groupBy('day_num')
                    ->pluck('count', 'day_num');

                foreach ($data as $dayNum => $count) {
                    $result[$dayNames[$dayNum - 1]] = $count;
                }
            }

            return $result;
        });
    }

    public function clearCache(): void
    {
        $keys = [
            'analytics.stat_cards',
            'analytics.subscription_distribution',
            'analytics.activity_heatmap',
            'analytics.user_growth.daily',
            'analytics.user_growth.weekly',
            'analytics.user_growth.monthly',
            'analytics.org_growth.daily',
            'analytics.org_growth.weekly',
            'analytics.org_growth.monthly',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * @param  class-string  $modelClass
     * @return array<string, int>
     */
    private function getGrowthData(string $modelClass, string $period): array
    {
        $days = match ($period) {
            'weekly' => 90,
            'monthly' => 365,
            default => 30,
        };

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $format = match ($period) {
                'weekly' => '%Y-%W',
                'monthly' => '%Y-%m',
                default => '%Y-%m-%d',
            };

            return $modelClass::query()
                ->where('created_at', '>=', now()->subDays($days))
                ->selectRaw("strftime('{$format}', created_at) as period, COUNT(*) as count")
                ->groupBy('period')
                ->orderBy('period')
                ->pluck('count', 'period')
                ->toArray();
        }

        $format = match ($period) {
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        return $modelClass::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as period, COUNT(*) as count")
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('count', 'period')
            ->toArray();
    }
}
