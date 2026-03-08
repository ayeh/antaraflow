<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Jobs;

use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LiveTranscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly LiveTranscriptChunk $chunk,
    ) {}

    public function handle(): void
    {
        // TODO: Implement live transcription processing in a future task
    }
}
