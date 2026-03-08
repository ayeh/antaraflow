<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Events;

use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveSessionStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LiveMeetingSession $session,
    ) {}

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("meeting.{$this->session->minutes_of_meeting_id}"),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'started_by' => $this->session->started_by,
            'started_at' => $this->session->started_at?->toIso8601String(),
        ];
    }
}
