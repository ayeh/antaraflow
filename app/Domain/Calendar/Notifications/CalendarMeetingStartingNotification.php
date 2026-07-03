<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CalendarMeetingStartingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public CarbonInterface $startsAt,
        public string $provider,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Meeting Starting Soon: {$this->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your {$this->provider} Calendar meeting **{$this->title}** starts at {$this->startsAt->copy()->setTimezone($notifiable->timezone ?: 'UTC')->format('g:i A')}.")
            ->line('You can start a live recording in antaraNote now.')
            ->action('Open antaraNote', route('calendar.connections'))
            ->line('Thank you for using antaraNote.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'calendar_meeting_starting',
            'title' => $this->title,
            'provider' => $this->provider,
            'starts_at' => $this->startsAt->toIso8601String(),
            'message' => "\"{$this->title}\" is starting soon",
        ];
    }
}
