<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Jobs;

use App\Domain\Calendar\Models\CalendarConnection;
use App\Domain\Calendar\Notifications\MeetingStartingSoonNotification;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class HandleMeetingStartJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public MinutesOfMeeting $meeting,
        public CalendarConnection $calendarConnection,
    ) {}

    public function handle(): void
    {
        if (! $this->calendarConnection->auto_record) {
            return;
        }

        // Notify meeting creator
        $creator = $this->meeting->createdBy;
        $creator?->notify(new MeetingStartingSoonNotification($this->meeting));

        // Notify all attendees with user_id set
        $attendees = $this->meeting->attendees()
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        foreach ($attendees as $attendee) {
            if ($attendee->user && $attendee->user->id !== $creator?->id) {
                $attendee->user->notify(new MeetingStartingSoonNotification($this->meeting));
            }
        }
    }
}
