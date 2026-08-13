<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Jobs;

use App\Domain\AI\Services\AiCircuitBreaker;
use App\Domain\AI\Services\AiUsageContext;
use App\Domain\AI\Services\OrgBudgetService;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Services\SpeakerDiarizationService;
use App\Infrastructure\AI\Exceptions\AiQuotaExceededException;
use App\Infrastructure\AI\Exceptions\OrgBudgetExceededException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Puts names to the speakers in a transcript that is already finished.
 *
 * `SpeakerDiarizationService` has existed and worked for a long time, and has
 * only ever been reachable from a controller somebody had to call by hand. A
 * live sitting therefore came back with every segment unattributed, on the one
 * path the product is built around.
 *
 * Everything here fails soft, and that is the whole design. The transcript is
 * already written, already merged, and already good enough to draft minutes
 * from — this is an improvement on top of it. A provider that is down, an
 * organisation that is out of budget, or a model that answers with prose
 * instead of JSON must all leave the meeting exactly as it would have been
 * without this job, and must never mark a recorded meeting as failed.
 */
class DiarizeTranscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Once only.
     *
     * Retrying costs another LLM call for a label that nobody is waiting on,
     * and the failures worth retrying — a provider blip — are the ones the
     * circuit breaker is already there to handle.
     */
    public int $tries = 1;

    /** Circuit breaker feature key, separate from transcription's own. */
    public const CIRCUIT = 'diarization';

    public function __construct(
        public readonly AudioTranscription $transcription,
    ) {
        $this->onQueue('live-transcription');
    }

    public function handle(): void
    {
        $organizationId = $this->transcription->minutesOfMeeting?->organization_id;

        try {
            app(OrgBudgetService::class)->guard($organizationId);
        } catch (OrgBudgetExceededException) {
            // Deliberately not a failure. An organisation over its budget has
            // its transcript; it does not get the names on top.
            return;
        }

        if (app(AiCircuitBreaker::class)->isOpen(self::CIRCUIT)) {
            return;
        }

        app(AiUsageContext::class)->set(
            organizationId: $organizationId,
            feature: self::CIRCUIT,
        );

        try {
            $labelled = app(SpeakerDiarizationService::class)->diarize($this->transcription);

            Log::info('Diarized a transcript.', [
                'transcription_id' => $this->transcription->id,
                'segments_labelled' => $labelled,
            ]);
        } catch (AiQuotaExceededException $e) {
            app(AiCircuitBreaker::class)->trip(self::CIRCUIT);

            Log::warning('Diarization paused; the provider rejected the request.', [
                'transcription_id' => $this->transcription->id,
                'message' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            // Swallowed on purpose, and logged rather than rethrown: a failure
            // here would mark the job failed and put a recorded meeting in the
            // failed-jobs table over a label.
            Log::warning('Diarization failed; the transcript keeps its unnamed speakers.', [
                'transcription_id' => $this->transcription->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
