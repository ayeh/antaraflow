<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Jobs;

use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Events\TranscriptionChunkProcessed;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
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

    public function handle(TranscriberInterface $transcriber): void
    {
        $this->chunk->update([
            'status' => ChunkStatus::Processing,
        ]);

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
        } catch (\Throwable $e) {
            $this->chunk->update([
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->chunk->update([
            'status' => ChunkStatus::Failed,
            'error_message' => $exception->getMessage(),
        ]);
    }
}
