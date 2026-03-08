<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Events;

use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranscriptionChunkProcessed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly LiveTranscriptChunk $chunk,
    ) {}

    /** @return array<int, \Illuminate\Broadcasting\Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("live-meeting.{$this->chunk->live_meeting_session_id}"),
        ];
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'chunk_id' => $this->chunk->id,
            'chunk_number' => $this->chunk->chunk_number,
            'text' => $this->chunk->text,
            'speaker' => $this->chunk->speaker,
            'start_time' => $this->chunk->start_time,
            'end_time' => $this->chunk->end_time,
            'confidence' => $this->chunk->confidence,
        ];
    }
}
