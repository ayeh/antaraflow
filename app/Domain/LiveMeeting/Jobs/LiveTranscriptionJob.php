<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Jobs;

use App\Domain\AI\Services\AiCircuitBreaker;
use App\Domain\AI\Services\AiUsageContext;
use App\Domain\AI\Services\OrgBudgetService;
use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Events\TranscriptionChunkProcessed;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Domain\Transcription\Services\AudioConditioner;
use App\Domain\Transcription\Services\RepetitionGuard;
use App\Domain\Transcription\Services\TranscriptionHintBuilder;
use App\Infrastructure\AI\DTOs\TranscriptionResult;
use App\Infrastructure\AI\DTOs\TranscriptionSegmentData;
use App\Infrastructure\AI\Exceptions\AiQuotaExceededException;
use App\Infrastructure\AI\Exceptions\OrgBudgetExceededException;
use App\Infrastructure\AI\TranscriberFactory;
use App\Support\Enums\TranscriptionMode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class LiveTranscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public readonly LiveTranscriptChunk $chunk,
    ) {
        $this->onQueue('live-transcription');
    }

    /** Circuit breaker feature key shared by every live transcription chunk. */
    public const CIRCUIT = 'live_transcription';

    /** How much of the previous chunk to replay as context for this one. */
    private const CONTEXT_CHAR_BUDGET = 400;

    public function handle(TranscriberFactory $transcribers): void
    {
        $transcriber = $transcribers->for(TranscriptionMode::Live);
        $breaker = app(AiCircuitBreaker::class);
        $organizationId = $this->chunk->session?->meeting?->organization_id;

        try {
            app(OrgBudgetService::class)->guard($organizationId);
        } catch (OrgBudgetExceededException $e) {
            $this->abandon($e);

            return;
        }

        if ($breaker->isOpen(self::CIRCUIT)) {
            $this->abandon(AiQuotaExceededException::make(__('Live transcription is paused until :time because the AI provider rejected recent requests.', [
                'time' => $breaker->openUntil(self::CIRCUIT),
            ])));

            return;
        }

        $this->chunk->update([
            'status' => ChunkStatus::Processing,
        ]);

        app(AiUsageContext::class)->set(
            organizationId: $organizationId,
            feature: self::CIRCUIT,
        );

        $conditioned = null;

        try {
            $filePath = Storage::disk('local')->path($this->chunk->audio_file_path);

            // A phone on a boardroom table hears the room, not a headset. Left
            // raw, quiet speech sits near the noise floor and comes back as
            // gaps; the same chunks conditioned land 15 dB louder without
            // clipping. The upload path has always done this — live never did.
            $conditioned = app(AudioConditioner::class)->condition(
                $filePath,
                timeoutSeconds: 30,
                logContext: ['chunk_id' => $this->chunk->id],
            );

            $meeting = $this->chunk->session?->meeting;
            $hints = app(TranscriptionHintBuilder::class);

            $result = $transcriber->transcribe($conditioned ?? $filePath, [
                'language' => $meeting?->language ?? 'en',
                'languages' => $hints->languagesFor($meeting),
                'keywords' => $hints->keywordsFor($meeting),
                'prompt' => $this->precedingContext(),
                'duration_seconds' => $this->chunk->end_time - $this->chunk->start_time,
            ]);

            // A chunk of silence or room noise comes back as one word looped for
            // its whole length. That is not speech, and — left in — its tail
            // becomes the next chunk's context prompt and teaches the model to
            // keep looping, so a single bad chunk fills the rest of the sitting.
            // Drop it to the same empty-but-transcribed state as a silent chunk.
            if (app(RepetitionGuard::class)->isDegenerate($result->fullText)) {
                $this->chunk->update([
                    'text' => '',
                    'segments' => [],
                    'speaker' => null,
                    'confidence' => $result->confidence,
                    'status' => ChunkStatus::Completed,
                ]);

                event(new TranscriptionChunkProcessed($this->chunk));

                return;
            }

            $speaker = $result->segments[0]->speaker ?? null;

            $this->chunk->update([
                'text' => $result->fullText,
                'segments' => $this->segmentsFrom($result),
                'speaker' => $speaker,
                'confidence' => $result->confidence,
                'status' => ChunkStatus::Completed,
            ]);

            event(new TranscriptionChunkProcessed($this->chunk));
        } catch (AiQuotaExceededException $e) {
            $breaker->trip(self::CIRCUIT);

            $this->abandon($e);
        } catch (\Throwable $e) {
            $this->chunk->update([
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            // A retried chunk conditions again, so nothing here is worth
            // keeping — and a sitting is hundreds of chunks on a shared box.
            if ($conditioned !== null) {
                @unlink($conditioned);
            }
        }
    }

    /**
     * The transcriber's segments, flattened for storage.
     *
     * Kept because a live sitting has never had any: the merge at the end of a
     * session joins chunk text into one block, so a recording made on a phone
     * arrives with no structure to attribute speakers to, and the diarization
     * the upload path enjoys has nothing to work on.
     *
     * Times stay on the chunk's own clock. They are shifted onto the meeting's
     * timeline at merge, where the chunk's `start_time` is read fresh — baking
     * the offset in here would survive a later correction to that value.
     *
     * An empty array, never null: a chunk the provider returned no segments for
     * has been transcribed, and must not be mistaken later for one that has not.
     *
     * @return array<int, array<string, mixed>>
     */
    private function segmentsFrom(TranscriptionResult $result): array
    {
        return array_map(static fn (TranscriptionSegmentData $segment): array => [
            'text' => $segment->text,
            'start_time' => $segment->startTime,
            'end_time' => $segment->endTime,
            'speaker' => $segment->speaker,
            'confidence' => $segment->confidence,
        ], $result->segments);
    }

    /**
     * The tail of the previous chunk's transcript.
     *
     * Recording is cut into fixed 30-second chunks, so sentences routinely
     * straddle a boundary and each chunk is otherwise transcribed blind.
     * Handing the model what was just said lets it carry the sentence — and
     * the vocabulary already established — across the cut.
     */
    private function precedingContext(): ?string
    {
        $previous = $this->chunk->session?->chunks()
            ->where('chunk_number', '<', $this->chunk->chunk_number)
            ->where('status', ChunkStatus::Completed)
            ->whereNotNull('text')
            ->orderByDesc('chunk_number')
            ->value('text');

        if (! $previous) {
            return null;
        }

        $tail = mb_substr(trim($previous), -self::CONTEXT_CHAR_BUDGET) ?: null;

        // Never hand the model a looped tail as context: even if the chunk it
        // came from was mostly real speech, priming the next chunk with a run of
        // one word is exactly what starts the loop propagating.
        if ($tail !== null && app(RepetitionGuard::class)->isDegenerate($tail)) {
            return null;
        }

        return $tail;
    }

    /**
     * Give up on this chunk without spending its remaining retries; the cause
     * is a standing condition that another attempt cannot clear.
     */
    private function abandon(\Throwable $e): void
    {
        $this->chunk->update([
            'status' => ChunkStatus::Failed,
            'error_message' => $e->getMessage(),
        ]);

        $this->fail($e);
    }

    public function failed(\Throwable $exception): void
    {
        $this->chunk->update([
            'status' => ChunkStatus::Failed,
            'error_message' => $exception->getMessage(),
        ]);
    }
}
