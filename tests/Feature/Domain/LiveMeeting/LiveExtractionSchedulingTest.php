<?php

declare(strict_types=1);

use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Events\TranscriptionChunkProcessed;
use App\Domain\LiveMeeting\Jobs\LiveExtractionJob;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function completedChunkFor(array $config): LiveTranscriptChunk
{
    $session = LiveMeetingSession::factory()->create([
        'status' => LiveSessionStatus::Active,
        'config' => $config,
    ]);

    Cache::forget("live-extraction:{$session->id}");

    return LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => 'Kita mula sekarang.',
    ]);
}

it('does not extract during a session that did not ask for it', function (): void {
    Queue::fake();

    $chunk = completedChunkFor(['live_extraction' => false, 'extraction_interval' => 300]);

    event(new TranscriptionChunkProcessed($chunk));

    Queue::assertNotPushed(LiveExtractionJob::class);
});

it('extracts once per interval however many chunks land', function (): void {
    Queue::fake();

    $chunk = completedChunkFor(['live_extraction' => true, 'extraction_interval' => 300]);

    // Chunks arrive every 30 seconds; extraction costs a full set of minutes.
    event(new TranscriptionChunkProcessed($chunk));
    event(new TranscriptionChunkProcessed($chunk));
    event(new TranscriptionChunkProcessed($chunk));

    Queue::assertPushed(LiveExtractionJob::class, 1);
});

it('opens the next window once the interval lapses', function (): void {
    Queue::fake();

    $chunk = completedChunkFor(['live_extraction' => true, 'extraction_interval' => 60]);

    event(new TranscriptionChunkProcessed($chunk));

    $this->travel(61)->seconds();

    event(new TranscriptionChunkProcessed($chunk));

    Queue::assertPushed(LiveExtractionJob::class, 2);
});

it('stops extracting once the session has ended', function (): void {
    Queue::fake();

    $chunk = completedChunkFor(['live_extraction' => true, 'extraction_interval' => 300]);
    $chunk->session->update(['status' => LiveSessionStatus::Ended]);

    event(new TranscriptionChunkProcessed($chunk->fresh()));

    Queue::assertNotPushed(LiveExtractionJob::class);
});

it('leaves the meeting content untouched while extracting', function (): void {
    $session = LiveMeetingSession::factory()->create(['status' => LiveSessionStatus::Active]);
    $session->meeting->update(['content' => 'The real agenda notes.']);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'status' => ChunkStatus::Completed,
        'text' => 'Transcript so far.',
    ]);

    $captured = null;

    $this->mock(App\Domain\AI\Services\ExtractionService::class)
        ->shouldReceive('extractAll')
        ->once()
        ->andReturnUsing(function ($meeting, $text) use (&$captured) {
            $captured = ['content' => $meeting->content, 'text' => $text];
        });

    (new LiveExtractionJob($session))->handle();

    // The transcript reaches the extractor without ever being written to the meeting.
    expect($captured['content'])->toBe('The real agenda notes.')
        ->and($captured['text'])->toContain('Transcript so far.')
        ->and($session->meeting->fresh()->content)->toBe('The real agenda notes.');
});
