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
            ->subject("Stale Decisions: {$this->meeting->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$count} decision(s) from **{$this->meeting->title}** have no follow-up action items.");

        foreach (array_slice($this->staleDecisions, 0, 3) as $decision) {
            $message->line("• {$decision['decision']} ({$decision['days_since']} days ago)");
        }

        if ($count > 3) {
            $message->line('...and '.($count - 3).' more.');
        }

        return $message
            ->action('View Meeting', route('meetings.show', $this->meeting))
            ->line('Please create action items or update the meeting to address these decisions.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'stale_decisions',
            'meeting_id' => $this->meeting->id,
            'meeting_title' => $this->meeting->title,
            'stale_count' => count($this->staleDecisions),
            'decisions' => array_map(fn ($d) => $d['decision'], array_slice($this->staleDecisions, 0, 5)),
        ];
    }
}
