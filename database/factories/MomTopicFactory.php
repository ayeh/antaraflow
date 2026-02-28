<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\AI\Models\MomTopic;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MomTopic> */
class MomTopicFactory extends Factory
{
    protected $model = MomTopic::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'duration_minutes' => fake()->numberBetween(5, 30),
            'sort_order' => fake()->numberBetween(0, 10),
            'related_segments' => null,
        ];
    }
}
