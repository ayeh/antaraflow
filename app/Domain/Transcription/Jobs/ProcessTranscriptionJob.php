<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Jobs;

use App\Domain\Transcription\Events\TranscriptionCompleted;
use App\Domain\Transcription\Events\TranscriptionFailed;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\DTOs\TranscriptionSegmentData;
use App\Support\Enums\TranscriptionStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ProcessTranscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /** Maximum file size in bytes for the Whisper API (25 MB). */
    private const MAX_FILE_SIZE = 25 * 1024 * 1024;

    public function __construct(
        public AudioTranscription $transcription,
    ) {}

    public function handle(TranscriberInterface $transcriber): void
    {
        $this->transcription->update([
            'status' => TranscriptionStatus::Processing,
            'started_at' => now(),
        ]);

        $compressedPath = null;

        try {
            $filePath = Storage::disk('local')->path($this->transcription->file_path);

            // Compress large files to fit within the Whisper API 25 MB limit
            if (file_exists($filePath) && filesize($filePath) > self::MAX_FILE_SIZE) {
                $filePath = $this->compressAudio($filePath);
                $compressedPath = $filePath;
            }

            $result = $transcriber->transcribe(
                $filePath,
                ['language' => $this->transcription->language]
            );

            $this->transcription->update([
                'status' => TranscriptionStatus::Completed,
                'full_text' => $result->fullText,
                'confidence_score' => $result->confidence,
                'completed_at' => now(),
            ]);

            $assignedSegments = $this->assignSpeakers($result->segments);

            foreach ($assignedSegments as $i => $segment) {
                $this->transcription->segments()->create([
                    'text' => $segment->text,
                    'speaker' => $segment->speaker,
                    'start_time' => $segment->startTime,
                    'end_time' => $segment->endTime,
                    'confidence' => $segment->confidence,
                    'sequence_order' => $i,
                ]);
            }

            event(new TranscriptionCompleted($this->transcription));
        } catch (\Throwable $e) {
            $this->transcription->update([
                'retry_count' => $this->transcription->retry_count + 1,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            // Clean up temporary compressed file
            if ($compressedPath && file_exists($compressedPath)) {
                @unlink($compressedPath);
            }
        }
    }

    /**
     * Assign speaker labels using time-gap heuristic.
     * A gap of more than $gapThreshold seconds between segments indicates a new speaker.
     *
     * @param  array<TranscriptionSegmentData>  $segments
     * @return array<TranscriptionSegmentData>
     */
    public function assignSpeakers(array $segments, float $gapThreshold = 1.5): array
    {
        $speakerIndex = 1;
        $previousEndTime = null;
        $result = [];

        foreach ($segments as $segment) {
            if ($previousEndTime !== null && ($segment->startTime - $previousEndTime) > $gapThreshold) {
                $speakerIndex++;
            }

            $result[] = new TranscriptionSegmentData(
                text: $segment->text,
                startTime: $segment->startTime,
                endTime: $segment->endTime,
                speaker: 'Speaker '.$speakerIndex,
                confidence: $segment->confidence,
            );

            $previousEndTime = $segment->endTime;
        }

        return $result;
    }

    /**
     * Compress audio to mono 16kHz MP3 to fit within the Whisper API size limit.
     * Whisper only uses 16kHz internally, so this is lossless for transcription quality.
     */
    private function compressAudio(string $filePath): string
    {
        $compressedPath = sys_get_temp_dir().'/whisper_'.uniqid().'.mp3';

        $result = Process::timeout(120)->run([
            'ffmpeg', '-i', $filePath,
            '-ac', '1',           // Mono
            '-ar', '16000',       // 16kHz (Whisper's native sample rate)
            '-b:a', '48k',        // 48kbps bitrate
            '-y',                 // Overwrite
            $compressedPath,
        ]);

        if ($result->failed() || ! file_exists($compressedPath)) {
            throw new \RuntimeException('Failed to compress audio: '.$result->errorOutput());
        }

        return $compressedPath;
    }

    public function failed(\Throwable $exception): void
    {
        $this->transcription->update([
            'status' => TranscriptionStatus::Failed,
            'error_message' => $exception->getMessage(),
            'retry_count' => $this->transcription->retry_count + 1,
        ]);

        event(new TranscriptionFailed($this->transcription));
    }
}
