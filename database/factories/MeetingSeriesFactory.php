<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MeetingSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeetingSeries> */
class MeetingSeriesFactory extends Factory
{
    protected $model = MeetingSeries::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->words(3, true).' Series',
            'description' => fake()->sentence(),
            'recurrence_pattern' => fake()->randomElement(['weekly', 'biweekly', 'monthly', null]),
            'recurrence_config' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
