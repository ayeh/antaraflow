<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Listeners;

use App\Domain\Transcription\Events\TranscriptionFailed;
use App\Domain\Transcription\Notifications\TranscriptionFailedNotification;
use App\Models\User;

class NotifyTranscriptionFailed
{
    public function handle(TranscriptionFailed $event): void
    {
        $meeting = $event->transcription->minutesOfMeeting;

        if (! $meeting) {
            return;
        }

        $creator = User::find($meeting->created_by);

        $creator?->notify(new TranscriptionFailedNotification($event->transcription));
    }
}
