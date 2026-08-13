<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Gets audio into the state speech recognition works best on, before it is sent.
 *
 * This existed only on the upload path. Everything recorded live — which is the
 * feature the product is built around — went to the transcriber exactly as the
 * room gave it. A hall recording measured a mean of -29 dBFS and came back with
 * a quarter of the words a close recording produced; the same chunks through
 * this chain land at -16 with peaks stopping cleanly at -1.5.
 */
class AudioConditioner
{
    /**
     * Rumble below 80 Hz carries no speech and eats headroom, so it goes first;
     * EBU R128 then brings the whole thing to a broadcast loudness with a true
     * peak ceiling, which is what stops the lift from clipping.
     */
    public const FILTER_CHAIN = 'highpass=f=80,loudnorm=I=-16:TP=-1.5:LRA=11';

    /** Mono 16 kHz is what speech recognition resamples to anyway. */
    public const SAMPLE_RATE = 16000;

    public const BITRATE = 32000;

    /**
     * Conditions [$filePath] into a new file and returns its path.
     *
     * Returns null when ffmpeg is missing or fails, so every caller can fall
     * back to sending the original. Untreated audio transcribes worse; no audio
     * transcribes not at all.
     *
     * The caller owns the returned file and must delete it.
     *
     * @param  array<string, mixed>  $logContext
     */
    public function condition(string $filePath, int $timeoutSeconds = 300, array $logContext = []): ?string
    {
        $outputPath = sys_get_temp_dir().'/conditioned_'.uniqid().'.ogg';

        $result = Process::timeout($timeoutSeconds)->run([
            'ffmpeg', '-hide_banner', '-loglevel', 'error',
            '-i', $filePath,
            '-af', self::FILTER_CHAIN,
            '-ar', (string) self::SAMPLE_RATE,
            '-ac', '1',
            '-c:a', 'libopus',
            '-b:a', (string) self::BITRATE,
            '-y',
            $outputPath,
        ]);

        if ($result->failed() || ! file_exists($outputPath) || filesize($outputPath) === 0) {
            Log::warning('Audio conditioning failed; using the original file.', [
                ...$logContext,
                'error' => $result->errorOutput(),
            ]);
            @unlink($outputPath);

            return null;
        }

        return $outputPath;
    }
}
