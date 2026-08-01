<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Listeners;

use App\Domain\LiveMeeting\Events\LiveTranscriptIncomplete;
use App\Domain\LiveMeeting\Notifications\LiveTranscriptIncompleteNotification;
use App\Models\User;

class NotifyLiveTranscriptIncomplete
{
    public function handle(LiveTranscriptIncomplete $event): void
    {
        $recipients = User::query()
            ->whereIn('id', array_filter([
                $event->session->started_by,
                $event->session->meeting?->created_by,
            ]))
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new LiveTranscriptIncompleteNotification($event));
        }
    }
}
