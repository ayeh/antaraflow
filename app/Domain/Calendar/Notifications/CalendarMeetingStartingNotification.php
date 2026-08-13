<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Notifications;

use App\Support\Traits\RespectsNotificationPreferences;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CalendarMeetingStartingNotification extends Notification implements ShouldQueue
{
    use Queueable, RespectsNotificationPreferences;

    protected function preferenceKey(): string
    {
        return 'meeting_starting';
    }

    public function __construct(
        public string $title,
        public CarbonInterface $startsAt,
        public string $provider,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferred($notifiable, ['mail', 'database']);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Meeting Starting Soon: :title', ['title' => $this->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Your :provider Calendar meeting **:title** starts at :time.', ['provider' => $this->provider, 'title' => $this->title, 'time' => $this->startsAt->copy()->setTimezone($notifiable->timezone ?: 'UTC')->format('g:i A')]))
            ->line(__('You can start a live recording in antaraNote now.'))
            ->action(__('Open antaraNote'), route('calendar.connections'))
            ->line(__('Thank you for using antaraNote.'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'calendar_meeting_starting',
            'title' => $this->title,
            'provider' => $this->provider,
            'starts_at' => $this->startsAt->toIso8601String(),
            'message' => __('":title" is starting soon', ['title' => $this->title]),
        ];
    }
}
