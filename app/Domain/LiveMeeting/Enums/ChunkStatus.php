<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Enums;

enum ChunkStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Recorded and kept, but deliberately never sent to the transcriber.
     *
     * Only ever a satellite chunk whose primary was heard perfectly well. The
     * audio is still on disk, so the decision can be revisited later against
     * the recording itself — skipped does not mean discarded.
     */
    case Skipped = 'skipped';

    /**
     * Whether this chunk is still on its way to being transcribed.
     *
     * The distinction that matters at merge: a pending chunk is audio we have
     * not got the words for yet, and a skipped one is audio we chose not to
     * ask about. Counting the second as the first tells somebody their
     * transcript has holes in it after every meeting that used a satellite.
     */
    public function isMissingWords(): bool
    {
        return $this === self::Pending
            || $this === self::Processing
            || $this === self::Failed;
    }
}
