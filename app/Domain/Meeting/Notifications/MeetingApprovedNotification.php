<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Notifications;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MinutesOfMeeting $meeting,
        public User $approvedBy,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Meeting Approved: {$this->meeting->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The meeting **{$this->meeting->title}** has been approved by {$this->approvedBy->name}.")
            ->action('View Meeting', route('meetings.show', $this->meeting));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'meeting_approved',
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'approved_by' => $this->approvedBy->name,
        ];
    }
}
