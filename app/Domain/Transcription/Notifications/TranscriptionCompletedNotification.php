<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Notifications;

use App\Domain\Transcription\Models\AudioTranscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TranscriptionCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AudioTranscription $transcription,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'transcription_completed',
            'transcription_id' => $this->transcription->id,
            'meeting_id' => $this->transcription->minutes_of_meeting_id,
            'meeting_title' => $this->transcription->minutesOfMeeting?->title,
        ];
    }
}
