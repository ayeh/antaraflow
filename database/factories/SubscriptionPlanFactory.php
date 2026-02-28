<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SubscriptionPlan> */
class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Free', 'Starter', 'Professional', 'Enterprise']);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price_monthly' => fake()->randomFloat(2, 0, 99),
            'price_yearly' => fake()->randomFloat(2, 0, 999),
            'features' => [
                'transcription' => true,
                'ai_summaries' => true,
                'export' => true,
            ],
            'max_users' => fake()->randomElement([1, 5, 25, 100]),
            'max_meetings_per_month' => fake()->randomElement([10, 50, 200, 1000]),
            'max_audio_minutes_per_month' => fake()->randomElement([60, 300, 1200, 6000]),
            'max_storage_mb' => fake()->randomElement([500, 2000, 10000, 50000]),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
