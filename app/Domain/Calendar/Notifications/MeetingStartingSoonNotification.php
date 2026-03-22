<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Notifications;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingStartingSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MinutesOfMeeting $meeting,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Meeting Starting Soon: {$this->meeting->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your meeting **{$this->meeting->title}** is starting soon.")
            ->line('Auto-recording is enabled for this calendar connection. You can start live recording now.')
            ->action('Start Live Recording', route('meetings.show', $this->meeting))
            ->line('Thank you for using AntaraFlow.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'meeting_starting_soon',
            'meeting_id' => $this->meeting->id,
            'meeting_title' => $this->meeting->title,
        ];
    }
}
