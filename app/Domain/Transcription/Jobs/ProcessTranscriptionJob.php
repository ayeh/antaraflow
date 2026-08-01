<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Jobs;

use App\Domain\AI\Services\AiUsageContext;
use App\Domain\AI\Services\OrgBudgetService;
use App\Domain\Transcription\Events\TranscriptionCompleted;
use App\Domain\Transcription\Events\TranscriptionFailed;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Infrastructure\AI\DTOs\TranscriptionSegmentData;
use App\Infrastructure\AI\TranscriberFactory;
use App\Support\Enums\TranscriptionMode;
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

    /** Lowest Opus bitrate worth attempting before giving up on a recording. */
    private const MIN_BITRATE = 6_000;

    /** Ceiling for the calculated bitrate; higher gains nothing for speech. */
    private const MAX_BITRATE = 48_000;

    /** Bitrate used when the source duration cannot be probed. */
    private const FALLBACK_BITRATE = 16_000;

    /** How many times to re-encode at a lower bitrate before failing. */
    private const MAX_COMPRESSION_ATTEMPTS = 3;

    public function __construct(
        public AudioTranscription $transcription,
    ) {}

    public function handle(TranscriberFactory $transcribers): void
    {
        $transcriber = $transcribers->for(TranscriptionMode::Upload);
        $organizationId = $this->transcription->minutesOfMeeting?->organization_id;
        app(OrgBudgetService::class)->guard($organizationId);

        app(AiUsageContext::class)->set(
            organizationId: $organizationId,
            userId: $this->transcription->uploaded_by,
            feature: 'transcription',
        );

        $this->transcription->update([
            'status' => TranscriptionStatus::Processing,
            'started_at' => now(),
        ]);

        $compressedPath = null;

        try {
            $filePath = Storage::disk('local')->path($this->transcription->file_path);

            // Compress large files to fit within the transcription size limit
            if (file_exists($filePath) && filesize($filePath) > self::MAX_FILE_SIZE) {
                $filePath = $this->compressAudio($filePath);
                $compressedPath = $filePath;
            }

            $result = $transcriber->transcribe($filePath, [
                'language' => $this->transcription->language,
                'duration_seconds' => $this->transcription->duration_seconds,
                'keywords' => $this->keywords(),
            ]);

            $this->transcription->update([
                'status' => TranscriptionStatus::Completed,
                'full_text' => $result->fullText,
                'confidence_score' => $result->confidence,
                'completed_at' => now(),
            ]);

            // A diarizing model already knows who spoke; the gap heuristic is
            // only a stand-in for providers that cannot tell us.
            $assignedSegments = $transcriber->supportsDiarization()
                ? $result->segments
                : $this->assignSpeakers($result->segments);

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
     * Proper nouns the recording is likely to contain, sent as recognition
     * hints. Attendee and organisation names are exactly what a general model
     * mishears, and they are the words a reader most notices getting wrong.
     *
     * @return array<int, string>
     */
    private function keywords(): array
    {
        $meeting = $this->transcription->minutesOfMeeting;

        if (! $meeting) {
            return [];
        }

        $keywords = $meeting->attendees()
            ->pluck('name')
            ->merge($meeting->attendees()->pluck('company'))
            ->push($meeting->title)
            ->filter()
            ->map(fn (string $value) => trim($value))
            ->reject(fn (string $value) => $value === '')
            ->unique()
            ->values();

        return $keywords->all();
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
     * Compress audio to mono 16kHz Opus so it fits within the transcription size limit.
     *
     * The bitrate is derived from the source duration, but a duration probe can
     * be wrong or unavailable, so the encoded result is measured and re-encoded
     * at a proportionally lower bitrate until it actually fits. Public so the
     * size guard can be exercised with a small $maxBytes in tests.
     */
    public function compressAudio(string $filePath, ?int $maxBytes = null): string
    {
        $maxBytes ??= self::MAX_FILE_SIZE;

        // Aim below the hard limit so container overhead cannot push us over.
        $targetBytes = (int) ($maxBytes * 0.8);

        $duration = $this->probeDuration($filePath);

        $bitrate = $duration > 0
            ? max(self::MIN_BITRATE, min(self::MAX_BITRATE, (int) ($targetBytes * 8 / $duration)))
            : self::FALLBACK_BITRATE;

        for ($attempt = 1; $attempt <= self::MAX_COMPRESSION_ATTEMPTS; $attempt++) {
            $compressedPath = $this->encodeToOpus($filePath, $bitrate);
            $size = filesize($compressedPath);

            if ($size !== false && $size <= $maxBytes) {
                return $compressedPath;
            }

            @unlink($compressedPath);

            if ($bitrate <= self::MIN_BITRATE || $size === false || $size <= 0) {
                break;
            }

            $bitrate = max(self::MIN_BITRATE, (int) ($bitrate * $targetBytes / $size));
        }

        throw new \RuntimeException(__('Audio could not be compressed under :size MB for transcription; the recording is too long.', [
            'size' => (int) round($maxBytes / 1024 / 1024),
        ]));
    }

    /** Source duration in seconds, or 0.0 when it cannot be determined. */
    private function probeDuration(string $filePath): float
    {
        $result = Process::timeout(30)->run([
            'ffprobe', '-v', 'quiet',
            '-show_entries', 'format=duration',
            '-of', 'csv=p=0',
            $filePath,
        ]);

        return $result->successful() ? (float) trim($result->output()) : 0.0;
    }

    /**
     * Encode to Opus/OGG, which Whisper accepts and which — unlike MP3 — honours
     * an arbitrary bitrate exactly rather than snapping to a quantized ladder.
     */
    private function encodeToOpus(string $filePath, int $bitrate): string
    {
        $compressedPath = sys_get_temp_dir().'/whisper_'.uniqid().'.ogg';

        $result = Process::timeout(300)->run([
            'ffmpeg', '-i', $filePath,
            '-c:a', 'libopus',
            '-ac', '1',                         // Mono
            '-ar', '16000',                     // 16kHz (Whisper's native sample rate)
            '-b:a', (string) $bitrate,
            '-y',                               // Overwrite
            $compressedPath,
        ]);

        if ($result->failed() || ! file_exists($compressedPath)) {
            throw new \RuntimeException(__('Failed to compress audio: :output', ['output' => $result->errorOutput()]));
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
