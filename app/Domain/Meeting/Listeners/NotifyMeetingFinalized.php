<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Listeners;

use App\Domain\Meeting\Events\MeetingFinalized;
use App\Domain\Meeting\Notifications\MeetingFinalizedNotification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyMeetingFinalized implements ShouldQueue
{
    public function handle(MeetingFinalized $event): void
    {
        $meeting = $event->meeting->load('attendees');

        foreach ($meeting->attendees as $attendee) {
            if ($attendee->user_id) {
                $user = User::find($attendee->user_id);
                $user?->notify(new MeetingFinalizedNotification($meeting, $event->finalizedBy));
            }
        }
    }
}
