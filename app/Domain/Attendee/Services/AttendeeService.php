<?php

declare(strict_types=1);

namespace App\Domain\Attendee\Services;

use App\Domain\Attendee\Models\AttendeeGroup;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\RsvpStatus;
use Illuminate\Support\Collection;

class AttendeeService
{
    public function addAttendee(MinutesOfMeeting $mom, array $data): MomAttendee
    {
        return MomAttendee::query()->create([
            'minutes_of_meeting_id' => $mom->id,
            ...$data,
        ]);
    }

    public function removeAttendee(MomAttendee $attendee): void
    {
        $attendee->delete();
    }

    public function updateRsvp(MomAttendee $attendee, RsvpStatus $status): MomAttendee
    {
        $attendee->update(['rsvp_status' => $status]);

        return $attendee->fresh();
    }

    public function markPresent(MomAttendee $attendee, bool $present = true): MomAttendee
    {
        $attendee->update(['is_present' => $present]);

        return $attendee->fresh();
    }

    public function bulkInviteFromGroup(MinutesOfMeeting $mom, AttendeeGroup $group): Collection
    {
        $members = $group->default_members ?? [];
        $created = collect();

        foreach ($members as $member) {
            $existing = MomAttendee::query()
                ->where('minutes_of_meeting_id', $mom->id)
                ->where('email', $member['email'] ?? null)
                ->exists();

            if (! $existing && isset($member['email'])) {
                $created->push($this->addAttendee($mom, [
                    'name' => $member['name'] ?? '',
                    'email' => $member['email'],
                    'role' => $member['role'] ?? 'participant',
                ]));
            }
        }

        return $created;
    }
}
