<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Meeting\Models\MeetingResolution;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\ResolutionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeetingResolution> */
class MeetingResolutionFactory extends Factory
{
    protected $model = MeetingResolution::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'meeting_id' => MinutesOfMeeting::factory(),
            'resolution_number' => 'RES-'.now()->format('Y').'-'.fake()->unique()->numberBetween(1, 9999),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'mover_id' => null,
            'seconder_id' => null,
            'status' => ResolutionStatus::Proposed,
        ];
    }

    public function passed(): static
    {
        return $this->state(['status' => ResolutionStatus::Passed]);
    }

    public function failed(): static
    {
        return $this->state(['status' => ResolutionStatus::Failed]);
    }

    public function tabled(): static
    {
        return $this->state(['status' => ResolutionStatus::Tabled]);
    }

    public function withdrawn(): static
    {
        return $this->state(['status' => ResolutionStatus::Withdrawn]);
    }
}
