<?php

declare(strict_types=1);

use App\Domain\AI\Services\ExtractionService;
use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Events\LiveExtractionUpdated;
use App\Domain\LiveMeeting\Jobs\LiveExtractionJob;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('extracts from accumulated transcript text', function () {
    Event::fake();

    $session = LiveMeetingSession::factory()->create();
    $meeting = $session->meeting;

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => 'First chunk of text.',
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 2,
        'text' => 'Second chunk of text.',
    ]);

    $originalContent = $meeting->content;

    $mockExtractionService = Mockery::mock(ExtractionService::class);
    $mockExtractionService->shouldReceive('extractAll')
        ->once()
        ->withArgs(function ($mom) {
            return str_contains($mom->content, 'First chunk of text.')
                && str_contains($mom->content, 'Second chunk of text.');
        });

    $this->app->instance(ExtractionService::class, $mockExtractionService);

    $job = new LiveExtractionJob($session);
    $job->handle();

    $meeting->refresh();
    expect($meeting->content)->toBe($originalContent);
});

test('broadcasts LiveExtractionUpdated event', function () {
    Event::fake();

    $session = LiveMeetingSession::factory()->create();

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => 'Some transcript text.',
    ]);

    $mockExtractionService = Mockery::mock(ExtractionService::class);
    $mockExtractionService->shouldReceive('extractAll')->once();

    $this->app->instance(ExtractionService::class, $mockExtractionService);

    $job = new LiveExtractionJob($session);
    $job->handle();

    Event::assertDispatched(LiveExtractionUpdated::class, function ($event) use ($session) {
        return $event->session->id === $session->id;
    });
});

test('skips extraction when no completed chunks', function () {
    Event::fake();

    $session = LiveMeetingSession::factory()->create();

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'status' => ChunkStatus::Pending,
        'text' => null,
    ]);

    $mockExtractionService = Mockery::mock(ExtractionService::class);
    $mockExtractionService->shouldNotReceive('extractAll');

    $this->app->instance(ExtractionService::class, $mockExtractionService);

    $job = new LiveExtractionJob($session);
    $job->handle();

    Event::assertNotDispatched(LiveExtractionUpdated::class);
});

test('restores original meeting content after extraction', function () {
    Event::fake();

    $session = LiveMeetingSession::factory()->create();
    $meeting = $session->meeting;
    $meeting->update(['content' => 'Original meeting notes here.']);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => 'Live transcript text.',
    ]);

    $contentDuringExtraction = null;
    $mockExtractionService = Mockery::mock(ExtractionService::class);
    $mockExtractionService->shouldReceive('extractAll')
        ->once()
        ->andReturnUsing(function ($mom) use (&$contentDuringExtraction) {
            $contentDuringExtraction = $mom->content;
        });

    $this->app->instance(ExtractionService::class, $mockExtractionService);

    $job = new LiveExtractionJob($session);
    $job->handle();

    $meeting->refresh();

    expect($contentDuringExtraction)->toContain('Live transcript text.')
        ->and($meeting->content)->toBe('Original meeting notes here.');
});
