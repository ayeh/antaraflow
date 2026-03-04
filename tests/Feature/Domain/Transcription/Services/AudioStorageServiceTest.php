<?php

declare(strict_types=1);

use App\Domain\Transcription\Services\AudioStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('stores an audio chunk with zero-padded index', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->create('chunk.webm', 100, 'audio/webm');
    $service = app(AudioStorageService::class);

    $path = $service->storeChunk($file, organizationId: 1, sessionId: 'abc-123', chunkIndex: 0);

    expect($path)->toBe('organizations/1/audio/chunks/abc-123/chunk_00000.webm')
        ->and(Storage::disk('local')->exists($path))->toBeTrue();
});

it('sorts chunks correctly when index exceeds single digits', function () {
    Storage::fake('local');

    $service = app(AudioStorageService::class);

    for ($i = 0; $i < 12; $i++) {
        $file = UploadedFile::fake()->create('chunk.webm', 50, 'audio/webm');
        $service->storeChunk($file, 1, 'sort-test', $i);
    }

    $files = collect(Storage::disk('local')->files('organizations/1/audio/chunks/sort-test'))
        ->sort()
        ->values()
        ->map(fn (string $f) => basename($f))
        ->all();

    expect($files[0])->toBe('chunk_00000.webm')
        ->and($files[1])->toBe('chunk_00001.webm')
        ->and($files[9])->toBe('chunk_00009.webm')
        ->and($files[10])->toBe('chunk_00010.webm')
        ->and($files[11])->toBe('chunk_00011.webm');
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

it('merges chunk content correctly without corruption', function () {
    Storage::fake('local');

    $service = app(AudioStorageService::class);
    $sessionId = 'content-test';
    $orgId = 1;

    $chunk1Content = random_bytes(100);
    $chunk2Content = random_bytes(100);

    Storage::disk('local')->put("organizations/1/audio/chunks/{$sessionId}/chunk_00000.webm", $chunk1Content);
    Storage::disk('local')->put("organizations/1/audio/chunks/{$sessionId}/chunk_00001.webm", $chunk2Content);

    $mergedPath = $service->mergeChunks($orgId, $sessionId, 'audio/webm');

    $mergedContent = Storage::disk('local')->get($mergedPath);
    expect($mergedContent)->toBe($chunk1Content.$chunk2Content);
});

it('throws an exception when merging with no chunks', function () {
    Storage::fake('local');

    $service = app(AudioStorageService::class);

    $service->mergeChunks(1, 'nonexistent-session', 'audio/webm');
})->throws(\RuntimeException::class, 'No chunks found for session nonexistent-session');
