<?php

declare(strict_types=1);

use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('session belongs to a meeting', function () {
    $session = LiveMeetingSession::factory()->create();

    expect($session->meeting)->toBeInstanceOf(MinutesOfMeeting::class)
        ->and($session->meeting->id)->toBe($session->minutes_of_meeting_id);
});

test('session belongs to the user who started it', function () {
    $user = User::factory()->create();
    $session = LiveMeetingSession::factory()->create(['started_by' => $user->id]);

    expect($session->startedBy)->toBeInstanceOf(User::class)
        ->and($session->startedBy->id)->toBe($user->id);
});

test('session has many chunks', function () {
    $session = LiveMeetingSession::factory()->create();

    LiveTranscriptChunk::factory()->count(3)->create([
        'live_meeting_session_id' => $session->id,
    ]);

    expect($session->chunks)->toHaveCount(3);
});

test('casts status as LiveSessionStatus enum', function () {
    $session = LiveMeetingSession::factory()->create(['status' => 'active']);

    expect($session->status)->toBeInstanceOf(LiveSessionStatus::class)
        ->and($session->status)->toBe(LiveSessionStatus::Active);
});

test('gets completed transcript text', function () {
    $session = LiveMeetingSession::factory()->create();

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 1,
        'text' => 'Hello everyone.',
        'status' => ChunkStatus::Completed,
    ]);

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 2,
        'text' => null,
        'status' => ChunkStatus::Completed,
    ]);

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 3,
        'text' => 'Let us begin.',
        'status' => ChunkStatus::Completed,
    ]);

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 4,
        'text' => 'Still processing...',
        'status' => ChunkStatus::Processing,
    ]);

    $transcript = $session->getCompletedTranscriptText();

    expect($transcript)->toBe("Hello everyone.\nLet us begin.");
});

test('checks if session is active', function () {
    $activeSession = LiveMeetingSession::factory()->create(['status' => LiveSessionStatus::Active]);
    $endedSession = LiveMeetingSession::factory()->create(['status' => LiveSessionStatus::Ended]);

    expect($activeSession->isActive())->toBeTrue()
        ->and($endedSession->isActive())->toBeFalse();
});
