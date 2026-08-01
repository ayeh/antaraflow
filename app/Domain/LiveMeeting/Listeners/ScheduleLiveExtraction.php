<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Listeners;

use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Events\TranscriptionChunkProcessed;
use App\Domain\LiveMeeting\Jobs\LiveExtractionJob;
use Illuminate\Support\Facades\Cache;

/**
 * Drives live extraction off the transcript as it arrives.
 *
 * Chunks land every thirty seconds but extraction costs roughly what a whole
 * meeting's minutes cost, so it runs on the session's own interval rather than
 * on every chunk — and only for sessions that asked for it.
 */
class ScheduleLiveExtraction
{
    private const DEFAULT_INTERVAL_SECONDS = 300;

    public function handle(TranscriptionChunkProcessed $event): void
    {
        $session = $event->chunk->session;

        if (! $session || $session->status !== LiveSessionStatus::Active) {
            return;
        }

        $config = $session->config ?? [];

        if (! ($config['live_extraction'] ?? false)) {
            return;
        }

        $interval = max(60, (int) ($config['extraction_interval'] ?? self::DEFAULT_INTERVAL_SECONDS));

        // Chunks are transcribed concurrently, so several can finish at once.
        // Cache::add only succeeds for the first, and the key expiring is what
        // opens the next window — no timestamp to store or clean up.
        if (! Cache::add($this->key($session->id), true, $interval)) {
            return;
        }

        LiveExtractionJob::dispatch($session);
    }

    private function key(int $sessionId): string
    {
        return "live-extraction:{$sessionId}";
    }
}
