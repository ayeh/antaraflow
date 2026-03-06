<?php

declare(strict_types=1);

namespace App\Events;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\MeetingStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MeetingStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly MinutesOfMeeting $meeting,
        public readonly MeetingStatus $newStatus,
        public readonly string $changedByName,
    ) {}

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("meeting.{$this->meeting->id}"),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'meeting_id' => $this->meeting->id,
            'status' => $this->newStatus->value,
            'changed_by' => $this->changedByName,
        ];
    }
}
