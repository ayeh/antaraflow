<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Jobs\ProcessTranscriptionJob;
use App\Models\User;
use App\Support\Enums\InputType;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

test('stores an audio chunk', function () {
    Storage::fake('local');

    $sessionId = Str::uuid()->toString();
    $chunk = UploadedFile::fake()->create('chunk.webm', 512, 'audio/webm');

    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.audio-chunks.store', $this->meeting), [
            'chunk' => $chunk,
            'session_id' => $sessionId,
            'chunk_index' => 0,
            'mime_type' => 'audio/webm',
        ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Chunk uploaded.',
            'chunk_index' => 0,
        ]);

    $expectedDir = "organizations/{$this->org->id}/audio/chunks/{$sessionId}";
    $files = Storage::disk('local')->files($expectedDir);
    expect($files)->toHaveCount(1);
});

test('validates chunk upload request', function () {
    Storage::fake('local');

    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.audio-chunks.store', $this->meeting), []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['chunk', 'session_id', 'chunk_index', 'mime_type']);
});

test('finalizes chunks into a single transcription', function () {
    Storage::fake('local');
    Queue::fake();

    $sessionId = Str::uuid()->toString();

    $chunk0 = UploadedFile::fake()->create('chunk0.webm', 512, 'audio/webm');
    $this->actingAs($this->user)
        ->postJson(route('meetings.audio-chunks.store', $this->meeting), [
            'chunk' => $chunk0,
            'session_id' => $sessionId,
            'chunk_index' => 0,
            'mime_type' => 'audio/webm',
        ])
        ->assertOk();

    $chunk1 = UploadedFile::fake()->create('chunk1.webm', 512, 'audio/webm');
    $this->actingAs($this->user)
        ->postJson(route('meetings.audio-chunks.store', $this->meeting), [
            'chunk' => $chunk1,
            'session_id' => $sessionId,
            'chunk_index' => 1,
            'mime_type' => 'audio/webm',
        ])
        ->assertOk();

    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.audio-chunks.finalize', $this->meeting), [
            'session_id' => $sessionId,
            'mime_type' => 'audio/webm',
            'duration_seconds' => 120,
            'language' => 'en',
        ]);

    $response->assertOk()
        ->assertJsonFragment(['message' => 'Recording finalized and transcription started.']);

    $this->assertDatabaseHas('audio_transcriptions', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
        'mime_type' => 'audio/webm',
        'duration_seconds' => 120,
        'language' => 'en',
    ]);

    $this->assertDatabaseHas('mom_inputs', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'type' => InputType::BrowserRecording->value,
    ]);

    Queue::assertPushed(ProcessTranscriptionJob::class);
});

test('deletes chunks when recording is cancelled', function () {
    Storage::fake('local');

    $sessionId = Str::uuid()->toString();

    $chunk = UploadedFile::fake()->create('chunk.webm', 512, 'audio/webm');
    $this->actingAs($this->user)
        ->postJson(route('meetings.audio-chunks.store', $this->meeting), [
            'chunk' => $chunk,
            'session_id' => $sessionId,
            'chunk_index' => 0,
            'mime_type' => 'audio/webm',
        ])
        ->assertOk();

    $chunkDir = "organizations/{$this->org->id}/audio/chunks/{$sessionId}";
    expect(Storage::disk('local')->files($chunkDir))->toHaveCount(1);

    $response = $this->actingAs($this->user)
        ->deleteJson(route('meetings.audio-chunks.destroy', $this->meeting), [
            'session_id' => $sessionId,
        ]);

    $response->assertOk()
        ->assertJson(['message' => 'Chunks deleted.']);

    expect(Storage::disk('local')->files($chunkDir))->toBeEmpty();
});

test('guest cannot upload audio chunks', function () {
    $response = $this->postJson(route('meetings.audio-chunks.store', $this->meeting), [
        'chunk' => UploadedFile::fake()->create('chunk.webm', 512, 'audio/webm'),
        'session_id' => Str::uuid()->toString(),
        'chunk_index' => 0,
        'mime_type' => 'audio/webm',
    ]);

    $response->assertUnauthorized();
});
