<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Notifications;

use App\Domain\Transcription\Models\AudioTranscription;
use App\Support\Traits\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TranscriptionFailedNotification extends Notification implements ShouldQueue
{
    use Queueable, RespectsNotificationPreferences;

    protected function preferenceKey(): string
    {
        return 'processing_failed';
    }

    public function __construct(
        public AudioTranscription $transcription,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferred($notifiable, ['database', 'mail']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Transcription Failed'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('A transcription has failed for your meeting.'))
            ->line(__('Please try uploading the audio file again or contact support.'))
            ->action(__('View Meeting'), route('meetings.show', $this->transcription->minutes_of_meeting_id));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $meetingTitle = $this->transcription->minutesOfMeeting?->title ?? __('Unknown Meeting');

        return [
            'type' => 'transcription_failed',
            'transcription_id' => $this->transcription->id,
            'meeting_id' => $this->transcription->minutes_of_meeting_id,
            'meeting_title' => $this->transcription->minutesOfMeeting?->title,
            'message' => __('Transcription failed for ":title"', ['title' => $meetingTitle]),
        ];
    }
}
