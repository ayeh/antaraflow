<?php

declare(strict_types=1);

use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function chunkWithAudio(LiveSessionStatus $sessionStatus, ChunkStatus $chunkStatus, int $daysOld): LiveTranscriptChunk
{
    $session = LiveMeetingSession::factory()->create(['status' => $sessionStatus]);
    $path = 'live/'.uniqid().'.webm';

    Storage::disk('local')->put($path, 'audio-bytes');

    return LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'status' => $chunkStatus,
        'audio_file_path' => $path,
        'created_at' => now()->subDays($daysOld),
    ]);
}

it('removes audio for transcribed chunks of ended sessions', function (): void {
    Storage::fake('local');

    $old = chunkWithAudio(LiveSessionStatus::Ended, ChunkStatus::Completed, 40);

    $this->artisan('live:prune-audio', ['--days' => 30])->assertSuccessful();

    Storage::disk('local')->assertMissing($old->audio_file_path);
    expect($old->fresh()->audio_file_path)->toBeNull();
});

it('keeps audio that is still within the retention window', function (): void {
    Storage::fake('local');

    $recent = chunkWithAudio(LiveSessionStatus::Ended, ChunkStatus::Completed, 5);

    $this->artisan('live:prune-audio', ['--days' => 30])->assertSuccessful();

    Storage::disk('local')->assertExists($recent->audio_file_path);
});

it('never touches a session that is still running', function (): void {
    Storage::fake('local');

    $live = chunkWithAudio(LiveSessionStatus::Active, ChunkStatus::Completed, 90);

    $this->artisan('live:prune-audio', ['--days' => 30])->assertSuccessful();

    Storage::disk('local')->assertExists($live->audio_file_path);
});

it('keeps audio for chunks that never transcribed, so they stay recoverable', function (): void {
    Storage::fake('local');

    $failed = chunkWithAudio(LiveSessionStatus::Ended, ChunkStatus::Failed, 90);

    $this->artisan('live:prune-audio', ['--days' => 30])->assertSuccessful();

    Storage::disk('local')->assertExists($failed->audio_file_path);

    $this->artisan('live:prune-audio', ['--days' => 30, '--include-failed' => true])->assertSuccessful();

    Storage::disk('local')->assertMissing($failed->audio_file_path);
});

it('reports without deleting on a dry run', function (): void {
    Storage::fake('local');

    $old = chunkWithAudio(LiveSessionStatus::Ended, ChunkStatus::Completed, 40);

    $this->artisan('live:prune-audio', ['--days' => 30, '--dry-run' => true])->assertSuccessful();

    Storage::disk('local')->assertExists($old->audio_file_path);
    expect($old->fresh()->audio_file_path)->not->toBeNull();
});
