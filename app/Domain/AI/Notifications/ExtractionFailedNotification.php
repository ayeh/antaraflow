<?php

declare(strict_types=1);

namespace App\Domain\AI\Notifications;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExtractionFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MinutesOfMeeting $meeting,
        public string $error,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("AI Extraction Failed: {$this->meeting->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("AI extraction failed for meeting: **{$this->meeting->title}**.")
            ->line('Please try running the extraction again.')
            ->action('View Meeting', route('meetings.show', $this->meeting));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'extraction_failed',
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'error' => $this->error,
        ];
    }
}
