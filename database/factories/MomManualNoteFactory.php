<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomManualNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MomManualNote> */
class MomManualNoteFactory extends Factory
{
    protected $model = MomManualNote::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(3),
            'content' => fake()->paragraphs(2, true),
            'sort_order' => 0,
        ];
    }
}
