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
}
