<?php

declare(strict_types=1);

namespace App\Domain\Attendee\Notifications;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingInviteNotification extends Notification implements ShouldQueue
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
        $message = (new MailMessage)
            ->subject("Meeting Invitation: {$this->meeting->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been invited to a meeting: **{$this->meeting->title}**.");

        if ($this->meeting->meeting_date) {
            $message->line("**Date:** {$this->meeting->meeting_date->format('M d, Y \\a\\t g:i A')}");
        }

        if ($this->meeting->location) {
            $message->line("**Location:** {$this->meeting->location}");
        }

        if ($this->meeting->duration_minutes) {
            $message->line("**Duration:** {$this->meeting->duration_minutes} minutes");
        }

        return $message
            ->action('View Meeting', route('meetings.show', $this->meeting))
            ->line('Please RSVP at your earliest convenience.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'meeting_invite',
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'meeting_date' => $this->meeting->meeting_date?->toIso8601String(),
        ];
    }
}
