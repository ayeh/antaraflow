<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MomVersion> */
class MomVersionFactory extends Factory
{
    protected $model = MomVersion::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'created_by' => User::factory(),
            'version_number' => 1,
            'content' => fake()->paragraphs(3, true),
            'change_summary' => fake()->sentence(),
            'snapshot' => [
                'title' => fake()->sentence(4),
                'summary' => fake()->paragraph(),
                'content' => fake()->paragraphs(3, true),
                'status' => 'draft',
                'metadata' => null,
            ],
        ];
    }
}
