<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Services;

use Illuminate\Http\UploadedFile;
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

        return $file->storeAs($path, "chunk_{$chunkIndex}.".$file->getClientOriginalExtension(), 'local');
    }

    public function mergeChunks(int $organizationId, string $sessionId, string $mimeType): string
    {
        $chunkDir = "organizations/{$organizationId}/audio/chunks/{$sessionId}";
        $disk = Storage::disk('local');
        $files = collect($disk->files($chunkDir))->sort()->values();

        $extension = match (true) {
            str_contains($mimeType, 'webm') => 'webm',
            str_contains($mimeType, 'mp4') => 'mp4',
            str_contains($mimeType, 'ogg') => 'ogg',
            default => 'webm',
        };

        $mergedFilename = 'recording_'.now()->format('Ymd_His').'.'.$extension;
        $mergedPath = "organizations/{$organizationId}/audio/{$mergedFilename}";

        $mergedContent = '';
        foreach ($files as $file) {
            $mergedContent .= $disk->get($file);
        }

        $disk->put($mergedPath, $mergedContent);

        $this->deleteChunks($organizationId, $sessionId);

        return $mergedPath;
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
