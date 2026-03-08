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
        $originalContent = $meeting->content;

        try {
            $meeting->content = $transcriptText;
            $meeting->saveQuietly();

            app(ExtractionService::class)->extractAll($meeting);
        } finally {
            $meeting->content = $originalContent;
            $meeting->saveQuietly();
        }

        $extractions = $meeting->extractions()->latest()->get()->toArray();

        event(new LiveExtractionUpdated($this->session, $extractions));
    }
}
