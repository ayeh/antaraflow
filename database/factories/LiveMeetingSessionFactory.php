<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LiveMeetingSession> */
class LiveMeetingSessionFactory extends Factory
{
    protected $model = LiveMeetingSession::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'started_by' => User::factory(),
            'status' => LiveSessionStatus::Active,
            'config' => null,
            'started_at' => now(),
            'paused_at' => null,
            'ended_at' => null,
            'total_duration_seconds' => null,
        ];
    }

    public function paused(): static
    {
        return $this->state([
            'status' => LiveSessionStatus::Paused,
            'paused_at' => now(),
        ]);
    }

    public function ended(): static
    {
        return $this->state([
            'status' => LiveSessionStatus::Ended,
            'ended_at' => now(),
            'total_duration_seconds' => fake()->numberBetween(300, 7200),
        ]);
    }
}
