<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Notifications;

use App\Domain\Transcription\Models\AudioTranscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TranscriptionFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public AudioTranscription $transcription,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Transcription Failed')
            ->greeting("Hello {$notifiable->name},")
            ->line('A transcription has failed for your meeting.')
            ->line('Please try uploading the audio file again or contact support.')
            ->action('View Meeting', route('meetings.show', $this->transcription->minutes_of_meeting_id));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'transcription_failed',
            'transcription_id' => $this->transcription->id,
            'meeting_id' => $this->transcription->minutes_of_meeting_id,
            'meeting_title' => $this->transcription->minutesOfMeeting?->title,
        ];
    }
}
