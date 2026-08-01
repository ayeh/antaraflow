<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class AudioStorageService
{
    public function store(UploadedFile $file, int $organizationId): string
    {
        $path = "organizations/{$organizationId}/audio";

        return $file->store($path, 'local');
    }

    public function delete(string $path): bool
    {
        return Storage::disk('local')->delete($path);
    }

    public function exists(string $path): bool
    {
        return Storage::disk('local')->exists($path);
    }

    public function getFullPath(string $path): string
    {
        return Storage::disk('local')->path($path);
    }

    public function storeChunk(UploadedFile $file, int $organizationId, string $sessionId, int $chunkIndex): string
    {
        $path = "organizations/{$organizationId}/audio/chunks/{$sessionId}";

        return $file->storeAs($path, sprintf('chunk_%05d.%s', $chunkIndex, $file->getClientOriginalExtension()), 'local');
    }

    public function mergeChunks(int $organizationId, string $sessionId, string $mimeType): string
    {
        $chunkDir = "organizations/{$organizationId}/audio/chunks/{$sessionId}";
        $disk = Storage::disk('local');
        $files = collect($disk->files($chunkDir))->sort()->values();

        if ($files->isEmpty()) {
            throw new \RuntimeException("No chunks found for session {$sessionId}");
        }

        $extension = match (true) {
            str_contains($mimeType, 'webm') => 'webm',
            str_contains($mimeType, 'mp4') => 'mp4',
            str_contains($mimeType, 'ogg') => 'ogg',
            default => 'webm',
        };

        $mergedFilename = 'recording_'.now()->format('Ymd_His').'.'.$extension;
        $mergedPath = "organizations/{$organizationId}/audio/{$mergedFilename}";

        $mergedFullPath = $disk->path($mergedPath);

        $dir = dirname($mergedFullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        /** @var array<int, string> $chunkFullPaths */
        $chunkFullPaths = $files->map(fn (string $file) => $disk->path($file))->all();

        if (! $this->concatWithFfmpeg($chunkFullPaths, $mergedFullPath)) {
            $this->concatRaw($chunkFullPaths, $mergedFullPath);
        }

        $this->deleteChunks($organizationId, $sessionId);

        return $mergedPath;
    }

    /**
     * Rebuild a single valid container from the per-chunk recordings.
     *
     * Each chunk is a complete media file with its own header, so concatenating
     * the bytes produces a container whose metadata describes only the first
     * chunk. Downstream duration probing then under-reports the recording
     * length, which throws off the transcription bitrate calculation. The
     * ffmpeg concat demuxer stitches the streams into one correct container.
     *
     * @param  array<int, string>  $chunkFullPaths
     */
    private function concatWithFfmpeg(array $chunkFullPaths, string $mergedFullPath): bool
    {
        $listPath = tempnam(sys_get_temp_dir(), 'concat_').'.txt';

        $list = implode("\n", array_map(
            fn (string $path) => "file '".str_replace("'", "'\\''", $path)."'",
            $chunkFullPaths,
        ));

        file_put_contents($listPath, $list."\n");

        try {
            $result = Process::timeout(300)->run([
                'ffmpeg', '-hide_banner', '-loglevel', 'error',
                '-f', 'concat',
                '-safe', '0',
                '-i', $listPath,
                '-c', 'copy',
                '-y',
                $mergedFullPath,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ffmpeg concat unavailable, falling back to raw merge.', [
                'error' => $e->getMessage(),
            ]);

            return false;
        } finally {
            @unlink($listPath);
        }

        if ($result->failed() || ! file_exists($mergedFullPath) || filesize($mergedFullPath) === 0) {
            Log::warning('ffmpeg concat failed, falling back to raw merge.', [
                'error' => $result->errorOutput(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Byte-level fallback used when ffmpeg is unavailable or rejects the input.
     *
     * @param  array<int, string>  $chunkFullPaths
     */
    private function concatRaw(array $chunkFullPaths, string $mergedFullPath): void
    {
        $outputStream = fopen($mergedFullPath, 'wb');

        foreach ($chunkFullPaths as $path) {
            $chunkStream = fopen($path, 'rb');
            stream_copy_to_stream($chunkStream, $outputStream);
            fclose($chunkStream);
        }

        fclose($outputStream);
    }

    public function deleteChunks(int $organizationId, string $sessionId): void
    {
        $chunkDir = "organizations/{$organizationId}/audio/chunks/{$sessionId}";
        $disk = Storage::disk('local');

        foreach ($disk->files($chunkDir) as $file) {
            $disk->delete($file);
        }

        $disk->deleteDirectory($chunkDir);
    }
}
