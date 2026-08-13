<?php

declare(strict_types=1);

namespace App\Domain\AI\Jobs;

use App\Domain\AI\Events\ExtractionCompleted;
use App\Domain\AI\Events\ExtractionFailed;
use App\Domain\AI\Services\ExtractionService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractMeetingDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public MinutesOfMeeting $meeting,
    ) {}

    public function handle(ExtractionService $service): void
    {
        $service->extractAll($this->meeting);

        // The web path has always run these two together — extraction writes
        // the items as text and structured data, and this turns them into real
        // ActionItem rows. Only the controller did the second half, so a
        // sitting recorded on a phone produced action items that existed in
        // the extraction and nowhere else: not on the Tasks tab, not assigned
        // to anybody, not counted as due.
        //
        // Attributed to whoever filed the sitting. A queued job has no
        // authenticated user, and the meeting's creator is the closest true
        // answer to "whose extraction was this".
        $author = $this->meeting->createdBy;

        if ($author !== null) {
            $service->createActionItemRecords($this->meeting, $author);
        }

        event(new ExtractionCompleted($this->meeting));
    }

    public function failed(\Throwable $exception): void
    {
        event(new ExtractionFailed($this->meeting, $exception->getMessage()));
    }
}
