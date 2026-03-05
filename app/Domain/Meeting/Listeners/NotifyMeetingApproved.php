<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Listeners;

use App\Domain\Meeting\Events\MeetingApproved;
use App\Domain\Meeting\Notifications\MeetingApprovedNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMeetingApproved implements ShouldQueue
{
    public function handle(MeetingApproved $event): void
    {
        $meeting = $event->meeting->load('attendees');

        foreach ($meeting->attendees as $attendee) {
            if ($attendee->user_id) {
                $user = User::find($attendee->user_id);
                $user?->notify(new MeetingApprovedNotification($meeting, $event->approvedBy));
            }
        }
    }
}
