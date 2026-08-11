<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Notifications;

use App\Domain\Transcription\Models\AudioTranscription;
use App\Infrastructure\Notifications\Messages\TeamsMessage;
use App\Infrastructure\Notifications\Push\PushMessage;
use App\Support\Traits\SendsMobilePush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TranscriptionCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsMobilePush;

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

        return $this->withPush($notifiable, $channels);
    }

    public function toPush(object $notifiable): PushMessage
    {
        return new PushMessage(
            title: __('Transcript ready'),
            body: $this->transcription->minutesOfMeeting?->title ?? __('Your recording'),
            deepLink: "antaraflow://meetings/{$this->transcription->minutes_of_meeting_id}",
        );
    }

    public function toTeams(object $notifiable): TeamsMessage
    {
        $meetingTitle = $this->transcription->minutesOfMeeting?->title ?? __('Unknown Meeting');

        return (new TeamsMessage)
            ->title(__('Transcription Completed'))
            ->content(__('Audio transcription has been completed for **:title**.', ['title' => $meetingTitle]))
            ->fact(__('Meeting'), $meetingTitle)
            ->action(__('View Meeting'), route('meetings.show', $this->transcription->minutes_of_meeting_id));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $meetingTitle = $this->transcription->minutesOfMeeting?->title ?? __('Unknown Meeting');

        return [
            'type' => 'transcription_completed',
            'transcription_id' => $this->transcription->id,
            'meeting_id' => $this->transcription->minutes_of_meeting_id,
            'meeting_title' => $this->transcription->minutesOfMeeting?->title,
            'message' => __('Transcription completed for ":title"', ['title' => $meetingTitle]),
        ];
    }
}
