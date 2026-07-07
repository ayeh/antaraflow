<?php

declare(strict_types=1);

namespace App\Domain\AI\Notifications;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaleDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array{decision: string, days_since: int}>  $staleDecisions
     */
    public function __construct(
        public MinutesOfMeeting $meeting,
        public array $staleDecisions,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->staleDecisions);

        $message = (new MailMessage)
            ->subject(__('Stale Decisions: :title', ['title' => $this->meeting->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':count decision(s) from **:title** have no follow-up action items.', ['count' => $count, 'title' => $this->meeting->title]));

        foreach (array_slice($this->staleDecisions, 0, 3) as $decision) {
            $message->line(__('• :decision (:days days ago)', ['decision' => $decision['decision'], 'days' => $decision['days_since']]));
        }

        if ($count > 3) {
            $message->line(__('...and :more more.', ['more' => $count - 3]));
        }

        return $message
            ->action(__('View Meeting'), route('meetings.show', $this->meeting))
            ->line(__('Please create action items or update the meeting to address these decisions.'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $count = count($this->staleDecisions);

        return [
            'type' => 'stale_decisions',
            'meeting_id' => $this->meeting->id,
            'meeting_title' => $this->meeting->title,
            'stale_count' => $count,
            'decisions' => array_map(fn ($d) => $d['decision'], array_slice($this->staleDecisions, 0, 5)),
            'message' => __(':count stale decision(s) in ":title"', ['count' => $count, 'title' => $this->meeting->title]),
        ];
    }
}
