<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Notifications;

use App\Domain\ActionItem\Models\ActionItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActionItemOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ActionItem $actionItem,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $daysOverdue = (int) $this->actionItem->due_date->diffInDays(now());

        $message = (new MailMessage)
            ->subject("Overdue Action Item: {$this->actionItem->title}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your action item **{$this->actionItem->title}** is overdue by {$daysOverdue} day(s).")
            ->line("**Due Date:** {$this->actionItem->due_date->format('M d, Y')}");

        if ($this->actionItem->description) {
            $message->line($this->actionItem->description);
        }

        if ($this->actionItem->meeting) {
            $message->action('View Meeting', route('meetings.show', $this->actionItem->meeting));
        }

        return $message->line('Please update the status or complete this action item.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'action_item_overdue',
            'action_item_id' => $this->actionItem->id,
            'title' => $this->actionItem->title,
            'due_date' => $this->actionItem->due_date->toIso8601String(),
            'days_overdue' => (int) $this->actionItem->due_date->diffInDays(now()),
        ];
    }
}
