<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MinutesOfMeeting> */
class MinutesOfMeetingFactory extends Factory
{
    protected $model = MinutesOfMeeting::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'summary' => fake()->paragraph(),
            'content' => fake()->paragraphs(3, true),
            'status' => MeetingStatus::Draft,
            'location' => fake()->address(),
            'meeting_date' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90, 120]),
            'metadata' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => MeetingStatus::Draft]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => MeetingStatus::InProgress]);
    }

    public function finalized(): static
    {
        return $this->state(['status' => MeetingStatus::Finalized]);
    }

    public function approved(): static
    {
        return $this->state(['status' => MeetingStatus::Approved]);
    }
}
