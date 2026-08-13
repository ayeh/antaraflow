<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();

    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->meeting = MinutesOfMeeting::createForOrganization($this->org->id, [
        'title' => 'Board meeting',
        'created_by' => $this->user->id,
    ]);
});

function m4aChunk(string $name = 'chunk.m4a'): UploadedFile
{
    // A real MP4 header, so the mimetypes rule sees what a phone actually sends
    // rather than the text/plain that UploadedFile::fake() would produce.
    $bytes = "\x00\x00\x00\x20ftypM4A \x00\x00\x00\x00M4A mp42isom\x00\x00\x00\x00"
        .str_repeat("\x00", 512);

    $path = tempnam(sys_get_temp_dir(), 'chunk');
    file_put_contents($path, $bytes);

    return new UploadedFile($path, $name, 'audio/mp4', null, true);
}

test('starting a session returns broadcast and upload guidance', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/meetings/{$this->meeting->id}/live/start", [
            'chunk_interval' => 15,
            'live_extraction' => true,
        ]);

    $response->assertCreated()
        ->assertJsonPath('session.status', 'active')
        ->assertJsonPath('session.config.chunk_interval', 15)
        ->assertJsonPath('broadcast.channel', fn (string $channel) => str_starts_with($channel, 'private-live-meeting.'))
        ->assertJsonStructure(['upload' => ['max_chunk_bytes', 'accepted_mimetypes']]);
});

test('starting a second session returns the running one so the app can rejoin', function () {
    $first = $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/meetings/{$this->meeting->id}/live/start")
        ->json('session.id');

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/meetings/{$this->meeting->id}/live/start")
        ->assertStatus(409)
        ->assertJsonPath('code', 'SESSION_ALREADY_ACTIVE')
        ->assertJsonPath('session.id', $first)
        ->assertJsonStructure(['resume' => ['next_chunk_number', 'missing_chunks', 'stats']]);
});

test('an m4a chunk recorded on a phone is accepted', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->post("/api/mobile/v1/live/{$session->id}/chunks", [
            'audio' => m4aChunk(),
            'chunk_number' => 0,
            'start_time' => 0,
            'end_time' => 15,
        ])
        ->assertCreated()
        ->assertJsonPath('chunk.chunk_number', 0)
        ->assertJsonPath('next_chunk_number', 1);
});

test('the level the phone measured is kept alongside the chunk', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->post("/api/mobile/v1/live/{$session->id}/chunks", [
            'audio' => m4aChunk(),
            'chunk_number' => 0,
            'start_time' => 0,
            'end_time' => 15,
            'peak_dbfs' => -12.4,
            'speech_dbfs' => -38.2,
            'noise_dbfs' => -61.7,
        ])
        ->assertCreated();

    $chunk = LiveTranscriptChunk::query()->firstOrFail();

    expect($chunk->peak_dbfs)->toBe(-12.4)
        ->and($chunk->speech_dbfs)->toBe(-38.2)
        ->and($chunk->noise_dbfs)->toBe(-61.7);
});

// Every client that predates the measurement, which is all of them, plus a
// chunk too short for the phone to have measured anything from.
test('a chunk that carries no measurement is stored without one', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->post("/api/mobile/v1/live/{$session->id}/chunks", [
            'audio' => m4aChunk(),
            'chunk_number' => 0,
            'start_time' => 0,
            'end_time' => 15,
        ])
        ->assertCreated();

    expect(LiveTranscriptChunk::query()->firstOrFail()->peak_dbfs)->toBeNull();
});

// The readings are the only evidence there will be about capture quality, so
// a client sending nonsense has to be turned away rather than averaged in.
test('a level outside the decibel scale is rejected', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/live/{$session->id}/chunks", [
            'audio' => m4aChunk(),
            'chunk_number' => 0,
            'start_time' => 0,
            'end_time' => 15,
            'speech_dbfs' => 14.0,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('speech_dbfs');
});

test('a repeated chunk is acknowledged rather than stored twice', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $payload = fn () => [
        'audio' => m4aChunk(),
        'chunk_number' => 3,
        'start_time' => 45,
        'end_time' => 60,
    ];

    $this->actingAs($this->user, 'sanctum')
        ->post("/api/mobile/v1/live/{$session->id}/chunks", $payload())
        ->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->post("/api/mobile/v1/live/{$session->id}/chunks", $payload())
        ->assertOk()
        ->assertJsonPath('code', 'CHUNK_DUPLICATE');

    expect(LiveTranscriptChunk::query()->where('live_meeting_session_id', $session->id)->count())->toBe(1);
});

test('a chunk is refused once the session is no longer active', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Ended,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->post("/api/mobile/v1/live/{$session->id}/chunks", [
            'audio' => m4aChunk(),
            'chunk_number' => 0,
            'start_time' => 0,
            'end_time' => 15,
        ])
        ->assertStatus(409)
        ->assertJsonPath('code', 'SESSION_NOT_ACTIVE');
});

test('state reports the gaps so the client can resend only what is missing', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    foreach ([0, 1, 3, 5] as $number) {
        LiveTranscriptChunk::query()->create([
            'live_meeting_session_id' => $session->id,
            'chunk_number' => $number,
            'text' => "chunk {$number}",
            'start_time' => $number * 15,
            'end_time' => ($number + 1) * 15,
            'status' => ChunkStatus::Completed,
        ]);
    }

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/mobile/v1/live/{$session->id}/state");

    $response->assertOk()
        ->assertJsonPath('missing_chunks', [2, 4])
        ->assertJsonPath('next_chunk_number', 6)
        ->assertJsonPath('stats.chunks_completed', 4);
});

test('state can return only chunks after a given number', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    foreach (range(0, 4) as $number) {
        LiveTranscriptChunk::query()->create([
            'live_meeting_session_id' => $session->id,
            'chunk_number' => $number,
            'text' => "chunk {$number}",
            'start_time' => $number * 15,
            'end_time' => ($number + 1) * 15,
            'status' => ChunkStatus::Completed,
        ]);
    }

    $response = $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/mobile/v1/live/{$session->id}/state?since_chunk=2");

    $response->assertOk();
    expect(collect($response->json('chunks'))->pluck('chunk_number')->all())->toBe([3, 4]);
});

test('a session can be paused and resumed', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/live/{$session->id}/pause")
        ->assertOk()
        ->assertJsonPath('session.status', 'paused');

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/live/{$session->id}/resume")
        ->assertOk()
        ->assertJsonPath('session.status', 'active');
});

test('resuming a session that is not paused is refused', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/live/{$session->id}/resume")
        ->assertStatus(409)
        ->assertJsonPath('code', 'SESSION_NOT_PAUSED');
});

test('a bookmark can be dropped mid-session and comes back with the state', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/live/{$session->id}/bookmarks", [
            'at_seconds' => 1432.5,
            'label' => 'Budget decision',
            'kind' => 'decision',
        ])
        ->assertCreated()
        ->assertJsonPath('bookmark.kind', 'decision');

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/mobile/v1/live/{$session->id}/state")
        ->assertOk()
        ->assertJsonPath('bookmarks.0.label', 'Budget decision');
});

test('a session in another organization is not reachable', function () {
    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['current_organization_id' => $otherOrg->id]);
    $otherOrg->members()->attach($otherUser, ['role' => UserRole::Owner->value]);

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $this->actingAs($otherUser, 'sanctum')
        ->getJson("/api/mobile/v1/live/{$session->id}/state")
        ->assertStatus(404);
});

test('accepts the same chunk number from a second device', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $post = fn (string $device, string $role) => $this->actingAs($this->user, 'sanctum')
        ->withHeader('Idempotency-Key', "chunk-{$session->id}-{$device}-4")
        ->post("/api/mobile/v1/live/{$session->id}/chunks", [
            'audio' => m4aChunk(),
            'chunk_number' => 4,
            'start_time' => 60,
            'end_time' => 75,
            'device_id' => $device,
            'role' => $role,
        ]);

    $post('laptop-at-the-head', 'primary')->assertCreated();
    $post('phone-at-the-far-end', 'satellite')->assertCreated();

    expect(LiveTranscriptChunk::query()->where('chunk_number', 4)->count())->toBe(2);
});

// The failure this whole task exists to prevent. The outbox drops a chunk on
// any 2xx and retries forever on anything else, so a duplicate answered as a
// server error wedges the queue permanently behind a chunk that can never
// succeed — and every second of the meeting after it is lost.
test('a device resending its own chunk is still told duplicate, not error', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $payload = fn () => [
        'audio' => m4aChunk(),
        'chunk_number' => 4,
        'start_time' => 60,
        'end_time' => 75,
        'device_id' => 'phone-at-the-far-end',
        'role' => 'satellite',
    ];

    $this->actingAs($this->user, 'sanctum')
        ->post("/api/mobile/v1/live/{$session->id}/chunks", $payload())
        ->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->post("/api/mobile/v1/live/{$session->id}/chunks", $payload())
        ->assertOk()
        ->assertJsonPath('code', 'CHUNK_DUPLICATE');

    expect(LiveTranscriptChunk::query()->where('chunk_number', 4)->count())->toBe(1);
});

// The race the check-then-insert has always had, which the new unique index
// now surfaces as an exception instead of a second row.
test('a chunk that lands between the check and the insert is a duplicate', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::query()->create([
        'live_meeting_session_id' => $session->id,
        'device_id' => 'phone-at-the-far-end',
        'role' => 'satellite',
        'chunk_number' => 4,
        'start_time' => 60,
        'end_time' => 75,
        'status' => ChunkStatus::Pending,
    ]);

    // The service is handed a session that has no such chunk in its loaded
    // relations, exactly as it would be mid-race.
    $chunk = app(\App\Domain\LiveMeeting\Services\LiveMeetingService::class)->processChunk(
        $session->fresh(),
        m4aChunk(),
        4,
        60.0,
        75.0,
        deviceId: 'phone-at-the-far-end',
        role: \App\Domain\LiveMeeting\Enums\ChunkRole::Satellite,
    );

    expect($chunk->wasRecentlyCreated)->toBeFalse()
        ->and(LiveTranscriptChunk::query()->where('chunk_number', 4)->count())->toBe(1);
});

test('each device resumes from its own place in the numbering', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    foreach ([0, 1, 2, 3] as $number) {
        LiveTranscriptChunk::factory()->create([
            'live_meeting_session_id' => $session->id,
            'device_id' => 'laptop-at-the-head',
            'role' => 'primary',
            'chunk_number' => $number,
        ]);
    }

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'device_id' => 'phone-at-the-far-end',
        'role' => 'satellite',
        'chunk_number' => 2,
    ]);

    $service = app(\App\Domain\LiveMeeting\Services\LiveMeetingService::class);

    expect($service->getResumeState($session, 'laptop-at-the-head')['next_chunk_number'])->toBe(4)
        ->and($service->getResumeState($session, 'phone-at-the-far-end')['next_chunk_number'])->toBe(3)
        // A device that has sent nothing starts at the beginning of its own
        // numbering, not at the primary's.
        ->and($service->getResumeState($session, 'a-phone-that-just-arrived')['next_chunk_number'])->toBe(0)
        // Without a device the answer is the whole session, which is what the
        // browser recorder asks for and must keep getting.
        ->and($service->getResumeState($session)['next_chunk_number'])->toBe(4);
});

test('the session stats stay session-wide, not per device', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'device_id' => 'laptop-at-the-head',
        'chunk_number' => 0,
    ]);
    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'device_id' => 'phone-at-the-far-end',
        'chunk_number' => 0,
    ]);

    $stats = app(\App\Domain\LiveMeeting\Services\LiveMeetingService::class)
        ->getResumeState($session, 'laptop-at-the-head')['stats'];

    expect($stats['chunks_total'])->toBe(2, 'the stats describe the sitting, not one microphone in it');
});

test('a joining device is told how far past the boundary the sitting is', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
        'config' => ['chunk_interval' => 15],
    ]);

    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'device_id' => 'laptop-at-the-head',
        'chunk_number' => 2,
    ]);
    $chunk->forceFill(['created_at' => now()->subSeconds(7)])->save();

    $resume = app(\App\Domain\LiveMeeting\Services\LiveMeetingService::class)
        ->getResumeState($session->fresh(), 'a-phone-that-just-arrived');

    // Seven seconds into the window that opened when chunk 2 arrived, so a
    // satellite has eight seconds to throw away before it is in step.
    expect($resume['seconds_into_chunk'])->toBeGreaterThan(6.5)
        ->and($resume['seconds_into_chunk'])->toBeLessThan(8.0);
});

// The primary died or lost signal. A satellite joining now should start clean
// at the next boundary rather than aim at a window that is never coming.
test('a sitting whose primary has stopped reports no offset at all', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
        'config' => ['chunk_interval' => 15],
    ]);

    $chunk = LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 2,
    ]);
    $chunk->forceFill(['created_at' => now()->subMinutes(4)])->save();

    $resume = app(\App\Domain\LiveMeeting\Services\LiveMeetingService::class)
        ->getResumeState($session->fresh());

    expect($resume['seconds_into_chunk'])->toBe(0.0);
});

test('a sitting with no chunks yet measures from when it started', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
        'started_at' => now()->subSeconds(3),
        'config' => ['chunk_interval' => 15],
    ]);

    $resume = app(\App\Domain\LiveMeeting\Services\LiveMeetingService::class)
        ->getResumeState($session);

    expect($resume['seconds_into_chunk'])->toBeGreaterThan(2.0)
        ->and($resume['seconds_into_chunk'])->toBeLessThan(4.5);
});

test('a second phone joining a running sitting is offered the satellite role', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'device_id' => 'laptop-at-the-head',
        'role' => 'primary',
        'chunk_number' => 0,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/meetings/{$this->meeting->id}/live/start", [
            'device_id' => 'phone-at-the-far-end',
        ])
        ->assertStatus(409)
        ->assertJsonPath('resume.role', 'satellite')
        ->assertJsonPath('resume.next_chunk_number', 0);
});

// The ordinary rejoin: the app was killed mid-sitting and came back. Demoting
// it to satellite would quietly stop it being the recording.
test('the device that was recording rejoins as the recording', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    foreach ([0, 1] as $number) {
        LiveTranscriptChunk::factory()->create([
            'live_meeting_session_id' => $session->id,
            'device_id' => 'laptop-at-the-head',
            'role' => 'primary',
            'chunk_number' => $number,
        ]);
    }

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/meetings/{$this->meeting->id}/live/start", [
            'device_id' => 'laptop-at-the-head',
        ])
        ->assertStatus(409)
        ->assertJsonPath('resume.role', 'primary')
        ->assertJsonPath('resume.next_chunk_number', 2);
});

test('a satellite that dropped out rejoins as a satellite', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'device_id' => 'laptop-at-the-head',
        'role' => 'primary',
        'chunk_number' => 0,
    ]);
    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'device_id' => 'phone-at-the-far-end',
        'role' => 'satellite',
        'chunk_number' => 0,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/meetings/{$this->meeting->id}/live/start", [
            'device_id' => 'phone-at-the-far-end',
        ])
        ->assertStatus(409)
        ->assertJsonPath('resume.role', 'satellite');
});

// Somebody pressed Record, the session opened, and the app died before a
// single chunk was uploaded. There is nothing to be a satellite to.
test('a device joining a sitting that recorded nothing is the recording', function () {
    LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/meetings/{$this->meeting->id}/live/start", [
            'device_id' => 'a-phone-that-just-arrived',
        ])
        ->assertStatus(409)
        ->assertJsonPath('resume.role', 'primary');
});

// The browser recorder and any app build predating satellites send no device.
// They have always been the recording and must stay so.
test('a client that names no device is still the recording', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    LiveTranscriptChunk::factory()->create([
        'live_meeting_session_id' => $session->id,
        'device_id' => 'laptop-at-the-head',
        'role' => 'primary',
        'chunk_number' => 0,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson("/api/mobile/v1/meetings/{$this->meeting->id}/live/start")
        ->assertStatus(409)
        ->assertJsonPath('resume.role', 'primary')
        ->assertJsonPath('resume.next_chunk_number', 1);
});
