<?php

declare(strict_types=1);

namespace App\Domain\AI\Notifications;

use App\Domain\AI\Models\MeetingPrepBrief;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingPrepBriefNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MeetingPrepBrief $brief,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->brief->meeting;
        $highlights = $this->brief->summary_highlights ?? [];

        $message = (new MailMessage)
            ->subject(__('Meeting Prep Brief: :title', ['title' => $meeting->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('Your meeting prep brief for **:title** is ready.', ['title' => $meeting->title]))
            ->line(__('**Meeting Date:** :date', ['date' => $meeting->meeting_date->format('M d, Y')]));

        foreach ($highlights as $highlight) {
            $message->line("- {$highlight}");
        }

        $message->line(__('**Estimated Prep Time:** :minutes minutes', ['minutes' => $this->brief->estimated_prep_minutes]));

        $message->action(__('View Prep Brief'), route('meetings.prep-brief', $meeting));

        return $message->line(__('Review the brief to be well-prepared for your meeting.'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'meeting_prep_brief',
            'meeting_prep_brief_id' => $this->brief->id,
            'meeting_id' => $this->brief->minutes_of_meeting_id,
            'meeting_title' => $this->brief->meeting->title,
            'estimated_prep_minutes' => $this->brief->estimated_prep_minutes,
            'message' => __('Meeting prep brief ready for ":title"', ['title' => $this->brief->meeting->title]),
        ];
    }
}
