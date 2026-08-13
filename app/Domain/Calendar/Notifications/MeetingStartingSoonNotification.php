<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Notifications;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Traits\RespectsNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingStartingSoonNotification extends Notification implements ShouldQueue
{
    use Queueable, RespectsNotificationPreferences;

    protected function preferenceKey(): string
    {
        return 'meeting_starting';
    }

    public function __construct(
        public MinutesOfMeeting $meeting,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferred($notifiable, ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Meeting Starting Soon: :title', ['title' => $this->meeting->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Your meeting **:title** is starting soon.', ['title' => $this->meeting->title]))
            ->line(__('Auto-recording is enabled for this calendar connection. You can start live recording now.'))
            ->action(__('Start Live Recording'), route('meetings.show', $this->meeting))
            ->line(__('Thank you for using antaraNote.'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'meeting_starting_soon',
            'meeting_id' => $this->meeting->id,
            'meeting_title' => $this->meeting->title,
            'message' => __('":title" is starting soon', ['title' => $this->meeting->title]),
        ];
    }
}
