<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Events;

use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\Transcription\Models\AudioTranscription;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A live session ended with chunks that never transcribed, so the minutes have
 * a gap. Raised once per session rather than per failed chunk — a provider
 * outage takes out every chunk it touches, and one notice about the gap is
 * useful where dozens are not.
 */
class LiveTranscriptIncomplete
{
    use Dispatchable;

    public function __construct(
        public readonly LiveMeetingSession $session,
        public readonly AudioTranscription $transcription,
        public readonly int $mergedChunks,
        public readonly int $droppedChunks,
    ) {}

    /** Approximate minutes of meeting audio missing from the transcript. */
    public function missingMinutes(): int
    {
        $seconds = $this->session->chunks()
            ->where('status', '!=', ChunkStatus::Completed)
            ->selectRaw('COALESCE(SUM(end_time - start_time), 0) as total')
            ->value('total');

        return (int) ceil(((float) $seconds) / 60);
    }
}
