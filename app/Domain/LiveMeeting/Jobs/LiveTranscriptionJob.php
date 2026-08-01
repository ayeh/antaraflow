<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Jobs;

use App\Domain\AI\Services\AiCircuitBreaker;
use App\Domain\AI\Services\AiUsageContext;
use App\Domain\AI\Services\OrgBudgetService;
use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Events\TranscriptionChunkProcessed;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\Exceptions\AiQuotaExceededException;
use App\Infrastructure\AI\Exceptions\OrgBudgetExceededException;
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

    public function handle(TranscriberInterface $transcriber): void
    {
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

        try {
            $filePath = Storage::disk('local')->path($this->chunk->audio_file_path);

            $language = $this->chunk->session?->meeting?->language ?? 'en';

            $result = $transcriber->transcribe($filePath, ['language' => $language]);

            $speaker = $result->segments[0]->speaker ?? null;

            $this->chunk->update([
                'text' => $result->fullText,
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
        }
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
