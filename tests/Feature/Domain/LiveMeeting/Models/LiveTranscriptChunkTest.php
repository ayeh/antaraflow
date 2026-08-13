<?php

declare(strict_types=1);

use App\Domain\LiveMeeting\Enums\ChunkRole;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->session = LiveMeetingSession::factory()->create();
});

// The whole point of a satellite microphone: two devices recording the same
// fifteen seconds of the same room. Reject this and the satellite's audio is
// thrown away while the upload is answered as a success.
test('two devices may hold the same chunk number', function () {
    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $this->session->id,
        'device_id' => 'laptop-in-the-corner',
        'chunk_number' => 7,
    ]);

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $this->session->id,
        'device_id' => 'phone-on-the-table',
        'chunk_number' => 7,
    ]);

    expect(LiveTranscriptChunk::query()->where('chunk_number', 7)->count())->toBe(2);
});

// Deduplication has been a check-then-insert with nothing behind it since the
// table was created. Two retries racing could always have stored the same
// audio twice, and billed for transcribing it twice.
test('one device may not hold the same chunk number twice', function () {
    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $this->session->id,
        'device_id' => 'phone-on-the-table',
        'chunk_number' => 7,
    ]);

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $this->session->id,
        'device_id' => 'phone-on-the-table',
        'chunk_number' => 7,
    ]);
})->throws(QueryException::class);

// The browser recorder sends no device at all, and every chunk recorded before
// this migration has none either. They are the session's one primary, and two
// of them in the same session is still the same collision it always was.
//
// This is why the column is not nullable: a unique index treats two NULLs as
// distinct, so a nullable device would leave exactly these rows unprotected.
test('a chunk with no device at all is still unique per number', function () {
    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $this->session->id,
        'device_id' => '',
        'chunk_number' => 7,
    ]);

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $this->session->id,
        'device_id' => '',
        'chunk_number' => 7,
    ]);
})->throws(QueryException::class);

test('the same chunk number in another session is untouched by any of this', function () {
    $other = LiveMeetingSession::factory()->create();

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $this->session->id,
        'device_id' => 'phone-on-the-table',
        'chunk_number' => 7,
    ]);

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $other->id,
        'device_id' => 'phone-on-the-table',
        'chunk_number' => 7,
    ]);

    expect(LiveTranscriptChunk::query()->where('chunk_number', 7)->count())->toBe(2);
});

test('a chunk is a primary unless it says otherwise', function () {
    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $this->session->id,
    ]);

    expect($chunk->fresh()->role)->toBe(ChunkRole::Primary);
});
