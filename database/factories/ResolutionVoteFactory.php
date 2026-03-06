<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MeetingResolution;
use App\Domain\Meeting\Models\ResolutionVote;
use App\Support\Enums\VoteChoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ResolutionVote> */
class ResolutionVoteFactory extends Factory
{
    protected $model = ResolutionVote::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'resolution_id' => MeetingResolution::factory(),
            'attendee_id' => MomAttendee::factory(),
            'vote' => fake()->randomElement(VoteChoice::cases()),
            'voted_at' => now(),
        ];
    }

    public function for(): static
    {
        return $this->state(['vote' => VoteChoice::For]);
    }

    public function against(): static
    {
        return $this->state(['vote' => VoteChoice::Against]);
    }

    public function abstain(): static
    {
        return $this->state(['vote' => VoteChoice::Abstain]);
    }
}
