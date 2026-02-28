<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrganizationSubscription> */
class OrganizationSubscriptionFactory extends Factory
{
    protected $model = OrganizationSubscription::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'subscription_plan_id' => SubscriptionPlan::factory(),
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'trial_ends_at' => null,
            'cancelled_at' => null,
            'metadata' => null,
        ];
    }

    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
