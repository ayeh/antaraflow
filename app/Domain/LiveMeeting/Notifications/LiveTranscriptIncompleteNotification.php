<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Notifications;

use App\Domain\LiveMeeting\Events\LiveTranscriptIncomplete;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LiveTranscriptIncompleteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public LiveTranscriptIncomplete $event,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = $this->event->missingMinutes();

        return (new MailMessage)
            ->subject(__('Part of your meeting was not transcribed'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('The live transcript for ":title" is incomplete.', ['title' => $this->meetingTitle()]))
            ->line(__(':dropped of :total audio segments could not be transcribed, leaving about :minutes minute(s) missing from the minutes.', [
                'dropped' => $this->event->droppedChunks,
                'total' => $this->event->droppedChunks + $this->event->mergedChunks,
                'minutes' => $minutes,
            ]))
            ->line(__('The audio itself was saved, so the missing part can still be recovered.'))
            ->action(__('View Meeting'), route('meetings.show', $this->event->session->minutes_of_meeting_id));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'live_transcript_incomplete',
            'session_id' => $this->event->session->id,
            'meeting_id' => $this->event->session->minutes_of_meeting_id,
            'meeting_title' => $this->meetingTitle(),
            'dropped_chunks' => $this->event->droppedChunks,
            'merged_chunks' => $this->event->mergedChunks,
            'missing_minutes' => $this->event->missingMinutes(),
            'message' => __('About :minutes minute(s) of ":title" could not be transcribed.', [
                'minutes' => $this->event->missingMinutes(),
                'title' => $this->meetingTitle(),
            ]),
        ];
    }

    private function meetingTitle(): string
    {
        return $this->event->session->meeting?->title ?? __('Unknown Meeting');
    }
}
