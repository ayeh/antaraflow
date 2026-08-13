<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Jobs;

use App\Domain\AI\Services\AiUsageContext;
use App\Domain\AI\Services\OrgBudgetService;
use App\Domain\Transcription\Events\TranscriptionCompleted;
use App\Domain\Transcription\Events\TranscriptionFailed;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Services\AudioConditioner;
use App\Domain\Transcription\Services\TranscriptionHintBuilder;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\DTOs\TranscriptionResult;
use App\Infrastructure\AI\DTOs\TranscriptionSegmentData;
use App\Infrastructure\AI\TranscriberFactory;
use App\Support\Enums\TranscriptionMode;
use App\Support\Enums\TranscriptionStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class ProcessTranscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /** Maximum file size in bytes for the Whisper API (25 MB). */
    private const MAX_FILE_SIZE = 25 * 1024 * 1024;

    /** Opus bitrate used for standard preprocessing (32 kbps ≈ 14.4 MB/hr, well within the API limit). */
    private const DEFAULT_BITRATE = 32_000;

    /** Lowest Opus bitrate worth attempting before giving up on a recording. */
    private const MIN_BITRATE = 6_000;

    /** Ceiling for the calculated bitrate; higher gains nothing for speech. */
    private const MAX_BITRATE = 48_000;

    /** Bitrate used when the source duration cannot be probed. */
    private const FALLBACK_BITRATE = 16_000;

    /** How many times to re-encode at a lower bitrate before failing. */
    private const MAX_COMPRESSION_ATTEMPTS = 3;

    /** Duration in seconds of each chunk when splitting an oversized file. */
    private const CHUNK_DURATION = 600;

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

        $processedPath = null;

        try {
            $filePath = Storage::disk('local')->path($this->transcription->file_path);

            // Normalise and enhance audio before transcription
            if (file_exists($filePath)) {
                $preprocessed = $this->preprocessAudio($filePath);
                if ($preprocessed !== $filePath) {
                    $processedPath = $preprocessed;
                    $filePath = $preprocessed;
                }
            }

            $meeting = $this->transcription->minutesOfMeeting;
            $hints = app(TranscriptionHintBuilder::class);

            $transcribeOptions = [
                'language' => $this->transcription->language,
                'languages' => $hints->languagesFor($meeting),
                'keywords' => $hints->keywordsFor($meeting),
                'duration_seconds' => $this->transcription->duration_seconds,
            ];

            $result = file_exists($filePath) && filesize($filePath) > self::MAX_FILE_SIZE
                ? $this->transcribeChunked($transcriber, $filePath, $transcribeOptions)
                : $transcriber->transcribe($filePath, $transcribeOptions);

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
            if ($processedPath && file_exists($processedPath)) {
                @unlink($processedPath);
            }
        }
    }

    /**
     * Split the audio into CHUNK_DURATION-second pieces and transcribe each one.
     *
     * Whisper's 25 MB file-size cap makes a single API call impractical for
     * recordings longer than roughly 90 minutes at 32 kbps. Splitting with
     * ffmpeg and transcribing each chunk avoids that limit; timestamps in each
     * result are shifted by the chunk's offset so the combined segments sit at
     * their true positions in the recording.
     *
     * @param  array<string, mixed>  $options
     */
    private function transcribeChunked(TranscriberInterface $transcriber, string $filePath, array $options): TranscriptionResult
    {
        $totalDuration = (float) ($options['duration_seconds'] ?? 0);
        $chunks = $totalDuration > 0
            ? (int) ceil($totalDuration / self::CHUNK_DURATION)
            : 1;

        $allSegments = [];
        $fullTexts = [];
        $detectedLanguage = null;
        $accumulatedDuration = 0;

        for ($i = 0; $i < $chunks; $i++) {
            $startSec = $i * self::CHUNK_DURATION;
            $chunkPath = sys_get_temp_dir().'/whisper_chunk_'.uniqid().'.ogg';

            try {
                $split = Process::timeout(120)->run([
                    'ffmpeg', '-hide_banner', '-loglevel', 'error',
                    '-ss', (string) $startSec,
                    '-t', (string) self::CHUNK_DURATION,
                    '-i', $filePath,
                    '-c', 'copy',
                    '-y', $chunkPath,
                ]);

                if ($split->failed() || ! file_exists($chunkPath) || filesize($chunkPath) === 0) {
                    Log::warning('Audio chunk split failed; skipping chunk.', [
                        'transcription_id' => $this->transcription->id,
                        'chunk' => $i,
                        'error' => $split->errorOutput(),
                    ]);

                    continue;
                }

                $chunkResult = $transcriber->transcribe(
                    $chunkPath,
                    array_merge($options, ['duration_seconds' => self::CHUNK_DURATION]),
                );

                foreach ($chunkResult->segments as $segment) {
                    $allSegments[] = new TranscriptionSegmentData(
                        text: $segment->text,
                        startTime: $segment->startTime + $startSec,
                        endTime: $segment->endTime + $startSec,
                        speaker: $segment->speaker,
                        confidence: $segment->confidence,
                    );
                }

                if ($chunkResult->fullText !== '') {
                    $fullTexts[] = $chunkResult->fullText;
                }

                if ($detectedLanguage === null) {
                    $detectedLanguage = $chunkResult->language;
                }

                $accumulatedDuration += $chunkResult->durationSeconds ?? self::CHUNK_DURATION;
            } finally {
                if (file_exists($chunkPath)) {
                    @unlink($chunkPath);
                }
            }
        }

        return new TranscriptionResult(
            fullText: implode(' ', $fullTexts),
            confidence: null,
            segments: $allSegments,
            language: $detectedLanguage,
            durationSeconds: $accumulatedDuration > 0 ? (int) round($accumulatedDuration) : (int) $totalDuration,
        );
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
     * Normalise audio quality and convert to the optimal format before transcription.
     *
     * Applies a highpass filter (removes low-frequency rumble below 80 Hz) and EBU R128
     * loudness normalisation, then encodes to mono 16 kHz Opus at 32 kbps. At that
     * rate a 7-hour recording fits within the 25 MB API limit, so compressAudio() is
     * only invoked as a safety net for extraordinary-length files.
     *
     * Returns the original path unchanged when ffmpeg is unavailable or fails so the
     * job can still proceed with the raw upload.
     */
    public function preprocessAudio(string $filePath): string
    {
        $outputPath = app(AudioConditioner::class)->condition($filePath, logContext: [
            'transcription_id' => $this->transcription->id,
        ]);

        if ($outputPath === null) {
            return $filePath;
        }

        if (filesize($outputPath) > self::MAX_FILE_SIZE) {
            try {
                $compressed = $this->compressAudio($outputPath);
            } finally {
                @unlink($outputPath);
            }

            return $compressed;
        }

        return $outputPath;
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
