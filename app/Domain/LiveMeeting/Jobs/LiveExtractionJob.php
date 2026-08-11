<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Jobs;

use App\Domain\AI\Services\ExtractionService;
use App\Domain\LiveMeeting\Events\LiveExtractionUpdated;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LiveExtractionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public readonly LiveMeetingSession $session,
    ) {
        $this->onQueue('live-extraction');
    }

    public function handle(): void
    {
        $transcriptText = $this->session->getCompletedTranscriptText();

        if (empty($transcriptText)) {
            return;
        }

        $meeting = $this->session->meeting;

        // The transcript is handed straight to the extractor. It used to be
        // written onto the meeting and restored afterwards, which showed the
        // wrong content to anyone viewing the meeting meanwhile and lost the
        // real content outright if the worker died in between.
        app(ExtractionService::class)->extractAll($meeting, $transcriptText);

        event(new LiveExtractionUpdated($this->session));
    }
}
