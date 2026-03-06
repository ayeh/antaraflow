<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Notifications;

use App\Domain\Transcription\Models\AudioTranscription;
use App\Infrastructure\Notifications\Messages\TeamsMessage;
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
        $channels = ['database'];

        if ($notifiable->currentOrganization?->hasTeamsWebhook()) {
            $channels[] = 'teams';
        }

        return $channels;
    }

    public function toTeams(object $notifiable): TeamsMessage
    {
        $meetingTitle = $this->transcription->minutesOfMeeting?->title ?? 'Unknown Meeting';

        return (new TeamsMessage)
            ->title('Transcription Completed')
            ->content("Audio transcription has been completed for **{$meetingTitle}**.")
            ->fact('Meeting', $meetingTitle)
            ->action('View Meeting', route('meetings.show', $this->transcription->minutes_of_meeting_id));
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
