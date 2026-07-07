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
            ->subject(__('AI Extraction Failed: :title', ['title' => $this->meeting->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('AI extraction failed for meeting: **:title**.', ['title' => $this->meeting->title]))
            ->line(__('Please try running the extraction again.'))
            ->action(__('View Meeting'), route('meetings.show', $this->meeting));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'extraction_failed',
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'error' => $this->error,
            'message' => __('AI extraction failed for ":title"', ['title' => $this->meeting->title]),
        ];
    }
}
