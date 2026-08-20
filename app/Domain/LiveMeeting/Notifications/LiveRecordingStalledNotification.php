<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Notifications;

use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Infrastructure\Notifications\Push\PushMessage;
use App\Support\Traits\RespectsNotificationPreferences;
use App\Support\Traits\SendsMobilePush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells whoever started a live meeting that its recording appears to have
 * stopped while the session is still open — the browser tab was almost
 * certainly backgrounded or closed and stopped sending audio. Sent once per
 * stall so the minute-by-minute watcher does not nag, and early enough that
 * the user can come back and resume before the session is auto-finalised.
 */
class LiveRecordingStalledNotification extends Notification implements ShouldQueue
{
    use Queueable, RespectsNotificationPreferences, SendsMobilePush;

    protected function preferenceKey(): string
    {
        return 'processing_failed';
    }

    public function __construct(
        public LiveMeetingSession $session,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferred($notifiable, $this->withPush($notifiable, ['database', 'mail']));
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your meeting recording may have stopped'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('The live recording for ":title" has not sent any audio for a few minutes, so it looks like it stopped — usually because the recording tab was closed or put to sleep.', ['title' => $this->meetingTitle()]))
            ->line(__('If the meeting is still going, open it and start recording again to keep capturing. What was already recorded is safe.'))
            ->action(__('Open Meeting'), route('meetings.show', $this->session->minutes_of_meeting_id));
    }

    public function toPush(object $notifiable): PushMessage
    {
        return new PushMessage(
            title: __('Recording may have stopped'),
            body: $this->meetingTitle(),
            deepLink: "antaraflow://meetings/{$this->session->minutes_of_meeting_id}",
        );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'live_recording_stalled',
            'session_id' => $this->session->id,
            'meeting_id' => $this->session->minutes_of_meeting_id,
            'meeting_title' => $this->meetingTitle(),
            'message' => __('Recording for ":title" appears to have stopped.', ['title' => $this->meetingTitle()]),
        ];
    }

    private function meetingTitle(): string
    {
        return $this->session->meeting?->title ?? __('Untitled meeting');
    }
}
