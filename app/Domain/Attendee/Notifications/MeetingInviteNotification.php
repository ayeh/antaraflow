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
            ->subject(__('Meeting Invitation: :title', ['title' => $this->meeting->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('You have been invited to a meeting: **:title**.', ['title' => $this->meeting->title]));

        if ($this->meeting->meeting_date) {
            $message->line(__('**Date:** :date', ['date' => $this->meeting->meeting_date->format('M d, Y \a\t g:i A')]));
        }

        if ($this->meeting->location) {
            $message->line(__('**Location:** :location', ['location' => $this->meeting->location]));
        }

        if ($this->meeting->duration_minutes) {
            $message->line(__('**Duration:** :minutes minutes', ['minutes' => $this->meeting->duration_minutes]));
        }

        return $message
            ->action(__('View Meeting'), route('meetings.show', $this->meeting))
            ->line(__('Please RSVP at your earliest convenience.'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'meeting_invite',
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'meeting_date' => $this->meeting->meeting_date?->toIso8601String(),
            'message' => __('You\'ve been invited to ":title"', ['title' => $this->meeting->title]),
        ];
    }
}
