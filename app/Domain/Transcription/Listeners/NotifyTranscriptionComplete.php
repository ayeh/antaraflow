<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Listeners;

use App\Domain\Transcription\Events\TranscriptionCompleted;
use App\Domain\Transcription\Notifications\TranscriptionCompletedNotification;
use App\Models\User;

class NotifyTranscriptionComplete
{
    public function handle(TranscriptionCompleted $event): void
    {
        $meeting = $event->transcription->minutesOfMeeting;

        if (! $meeting) {
            return;
        }

        $creator = User::find($meeting->created_by);

        $creator?->notify(new TranscriptionCompletedNotification($event->transcription));
    }
}
