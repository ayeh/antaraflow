<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\ActionItem\Models\ActionItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActionItemUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ActionItem $actionItem,
    ) {}

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("meeting.{$this->actionItem->minutes_of_meeting_id}"),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->actionItem->id,
            'title' => $this->actionItem->title,
            'status' => $this->actionItem->status->value,
            'priority' => $this->actionItem->priority?->value,
            'assigned_to' => $this->actionItem->assigned_to,
            'assigned_to_name' => $this->actionItem->assignedTo?->name,
            'due_date' => $this->actionItem->due_date?->toIso8601String(),
        ];
    }
}
