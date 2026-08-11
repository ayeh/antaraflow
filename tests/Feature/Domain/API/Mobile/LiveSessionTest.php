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
