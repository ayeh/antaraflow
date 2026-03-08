<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Events;

use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveExtractionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $extractions
     */
    public function __construct(
        public readonly LiveMeetingSession $session,
        public readonly array $extractions,
    ) {}

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("live-meeting.{$this->session->id}"),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'extractions' => $this->extractions,
        ];
    }
}
