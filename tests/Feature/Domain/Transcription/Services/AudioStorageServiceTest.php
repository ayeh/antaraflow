<?php

declare(strict_types=1);

use App\Domain\Transcription\Services\AudioStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores an audio chunk to the correct path', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->create('chunk.webm', 100, 'audio/webm');
    $service = app(AudioStorageService::class);

    $path = $service->storeChunk($file, organizationId: 1, sessionId: 'abc-123', chunkIndex: 0);

    expect($path)->toContain('organizations/1/audio/chunks/abc-123/')
        ->and(Storage::disk('local')->exists($path))->toBeTrue();
});

it('merges audio chunks into a single file', function () {
    Storage::fake('local');

    $service = app(AudioStorageService::class);
    $sessionId = 'merge-test';
    $orgId = 1;

    for ($i = 0; $i < 3; $i++) {
        $file = UploadedFile::fake()->create("chunk_{$i}.webm", 50, 'audio/webm');
        $service->storeChunk($file, $orgId, $sessionId, $i);
    }

    $mergedPath = $service->mergeChunks($orgId, $sessionId, 'audio/webm');

    expect($mergedPath)->toContain('organizations/1/audio/')
        ->and($mergedPath)->not->toContain('chunks/')
        ->and(Storage::disk('local')->exists($mergedPath))->toBeTrue();
});

it('deletes all chunks for a session', function () {
    Storage::fake('local');

    $service = app(AudioStorageService::class);
    $sessionId = 'delete-test';

    $file = UploadedFile::fake()->create('chunk.webm', 50, 'audio/webm');
    $service->storeChunk($file, 1, $sessionId, 0);

    $service->deleteChunks(1, $sessionId);

    $chunkDir = "organizations/1/audio/chunks/{$sessionId}";
    expect(Storage::disk('local')->files($chunkDir))->toBeEmpty();
});
