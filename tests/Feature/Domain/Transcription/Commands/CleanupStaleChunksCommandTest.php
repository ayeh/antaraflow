<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

it('deletes chunk directories when using --hours=0 to treat all as stale', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $disk->put('organizations/1/audio/chunks/old-session/chunk_00000.webm', 'data');

    $this->artisan('transcription:cleanup-chunks', ['--hours' => 0])
        ->assertSuccessful()
        ->expectsOutputToContain('Cleaned up 1 stale chunk directories');

    expect($disk->exists('organizations/1/audio/chunks/old-session'))->toBeFalse();
});

it('preserves recent chunk directories', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $disk->put('organizations/1/audio/chunks/recent-session/chunk_00000.webm', 'data');

    $this->artisan('transcription:cleanup-chunks')
        ->assertSuccessful();

    expect($disk->exists('organizations/1/audio/chunks/recent-session/chunk_00000.webm'))->toBeTrue();
});

it('deletes empty session directories', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $disk->makeDirectory('organizations/1/audio/chunks/empty-session');

    $this->artisan('transcription:cleanup-chunks')
        ->assertSuccessful()
        ->expectsOutputToContain('Cleaned up 1 stale chunk directories');
});

it('handles multiple organizations and sessions', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $disk->put('organizations/1/audio/chunks/session-a/chunk_00000.webm', 'data');
    $disk->put('organizations/2/audio/chunks/session-b/chunk_00000.webm', 'data');
    $disk->put('organizations/2/audio/chunks/session-c/chunk_00000.webm', 'data');

    $this->artisan('transcription:cleanup-chunks', ['--hours' => 0])
        ->assertSuccessful()
        ->expectsOutputToContain('Cleaned up 3 stale chunk directories');

    expect($disk->exists('organizations/1/audio/chunks/session-a'))->toBeFalse()
        ->and($disk->exists('organizations/2/audio/chunks/session-b'))->toBeFalse()
        ->and($disk->exists('organizations/2/audio/chunks/session-c'))->toBeFalse();
});

it('ignores directories without an audio/chunks path', function () {
    Storage::fake('local');
    $disk = Storage::disk('local');

    $disk->put('organizations/1/documents/report.pdf', 'data');

    $this->artisan('transcription:cleanup-chunks', ['--hours' => 0])
        ->assertSuccessful()
        ->expectsOutputToContain('Cleaned up 0 stale chunk directories');

    expect($disk->exists('organizations/1/documents/report.pdf'))->toBeTrue();
});

it('reports zero deletions when no chunks exist', function () {
    Storage::fake('local');

    $this->artisan('transcription:cleanup-chunks')
        ->assertSuccessful()
        ->expectsOutputToContain('Cleaned up 0 stale chunk directories');
});
