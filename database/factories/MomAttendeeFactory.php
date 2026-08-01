<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\AttendeeRole;
use App\Support\Enums\RsvpStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MomAttendee> */
class MomAttendeeFactory extends Factory
{
    protected $model = MomAttendee::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'minutes_of_meeting_id' => MinutesOfMeeting::factory(),
            'user_id' => User::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => AttendeeRole::Participant,
            'position' => null,
            'organization_unit' => null,
            'rsvp_status' => RsvpStatus::Pending,
            'is_present' => false,
            'is_external' => false,
            'department' => fake()->word(),
            'attendance_group' => 'present',
            'annotation' => null,
            'absence_reason' => null,
            'sort_order' => 0,
        ];
    }

    public function external(): static
    {
        return $this->state([
            'is_external' => true,
            'user_id' => null,
        ]);
    }

    public function present(): static
    {
        return $this->state([
            'is_present' => true,
            'attendance_group' => 'present',
            'rsvp_status' => RsvpStatus::Accepted,
        ]);
    }

    public function alsoPresent(): static
    {
        return $this->state([
            'is_present' => true,
            'attendance_group' => 'also_present',
            'rsvp_status' => RsvpStatus::Accepted,
        ]);
    }

    public function absent(): static
    {
        return $this->state([
            'is_present' => false,
            'attendance_group' => 'absent',
            'rsvp_status' => RsvpStatus::Declined,
        ]);
    }

    public function organizer(): static
    {
        return $this->state([
            'role' => AttendeeRole::Organizer,
            'annotation' => '-Pengerusi-',
        ]);
    }

    public function noteTaker(): static
    {
        return $this->state([
            'role' => AttendeeRole::NoteTaker,
            'annotation' => '-Pencatit minit-',
        ]);
    }
}
