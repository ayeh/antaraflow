<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Notifications;

use App\Domain\ActionItem\Models\ActionItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ActionItemAssignedNotification extends Notification implements ShouldQueue
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
        $message = (new MailMessage)
            ->subject(__('New Action Item Assigned: :title', ['title' => $this->actionItem->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('You have been assigned a new action item: **:title**.', ['title' => $this->actionItem->title]));

        if ($this->actionItem->description) {
            $message->line($this->actionItem->description);
        }

        if ($this->actionItem->due_date) {
            $message->line(__('**Due Date:** :date', ['date' => $this->actionItem->due_date->format('M d, Y')]));
        }

        if ($this->actionItem->meeting) {
            $message->action(__('View Meeting'), route('meetings.show', $this->actionItem->meeting));
        }

        return $message->line(__('Please review and take action as needed.'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'action_item_assigned',
            'action_item_id' => $this->actionItem->id,
            'title' => $this->actionItem->title,
            'meeting_title' => $this->actionItem->meeting?->title,
            'message' => __('You\'ve been assigned: ":title"', ['title' => $this->actionItem->title]),
        ];
    }
}
