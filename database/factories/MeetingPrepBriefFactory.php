<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeetingPrepBrief> */
class MeetingPrepBriefFactory extends Factory
{
    protected $model = MeetingPrepBrief::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $meeting = MinutesOfMeeting::factory()->create();
        $attendee = MomAttendee::factory()->create([
            'minutes_of_meeting_id' => $meeting->id,
        ]);

        return [
            'minutes_of_meeting_id' => $meeting->id,
            'attendee_id' => $attendee->id,
            'user_id' => $attendee->user_id,
            'content' => [
                'executive_summary' => fake()->paragraph(),
                'action_items' => [],
                'unresolved_items' => [],
                'agenda_deep_dive' => [],
                'metrics' => [],
                'reading_list' => [],
                'conflicts' => [],
            ],
            'summary_highlights' => [
                fake()->sentence(),
                fake()->sentence(),
                fake()->sentence(),
            ],
            'estimated_prep_minutes' => fake()->numberBetween(10, 60),
            'generated_at' => now(),
            'email_sent_at' => null,
            'viewed_at' => null,
            'sections_read' => null,
        ];
    }
}
