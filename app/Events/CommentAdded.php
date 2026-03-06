<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Collaboration\Models\Comment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CommentAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Comment $comment,
        public readonly int $meetingId,
    ) {}

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("meeting.{$this->meetingId}"),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->comment->id,
            'body' => $this->comment->body,
            'user_id' => $this->comment->user_id,
            'user_name' => $this->comment->user?->name,
            'parent_id' => $this->comment->parent_id,
            'created_at' => $this->comment->created_at?->toIso8601String(),
        ];
    }
}
