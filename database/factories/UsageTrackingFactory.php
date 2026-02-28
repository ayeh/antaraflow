<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\UsageTracking;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UsageTracking> */
class UsageTrackingFactory extends Factory
{
    protected $model = UsageTracking::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'metric' => fake()->randomElement(['meetings', 'audio_minutes', 'storage_mb', 'api_calls']),
            'value' => fake()->randomFloat(2, 0, 1000),
            'period' => now()->format('Y-m'),
            'metadata' => null,
        ];
    }
}
