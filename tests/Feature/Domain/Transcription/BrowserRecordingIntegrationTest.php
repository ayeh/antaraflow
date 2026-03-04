<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Jobs\ProcessTranscriptionJob;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Models\User;
use App\Support\Enums\InputType;
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
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

it('handles short recording upload via existing transcription endpoint with json', function () {
    $file = UploadedFile::fake()->create('recording.mp3', 500, 'audio/mpeg');

    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.transcriptions.store', $this->meeting), [
            'audio' => $file,
            'language' => 'en',
        ]);

    $response->assertOk();
    $response->assertJsonStructure(['message', 'transcription']);

    $this->assertDatabaseHas('audio_transcriptions', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
    ]);

    Queue::assertPushed(ProcessTranscriptionJob::class);
});

it('handles full chunked recording lifecycle', function () {
    $sessionId = fake()->uuid();

    for ($i = 0; $i < 3; $i++) {
        $chunk = UploadedFile::fake()->create("chunk_{$i}.webm", 100, 'audio/webm');

        $response = $this->actingAs($this->user)
            ->postJson(route('meetings.audio-chunks.store', $this->meeting), [
                'chunk' => $chunk,
                'session_id' => $sessionId,
                'chunk_index' => $i,
                'mime_type' => 'audio/webm',
            ]);

        $response->assertOk();
    }

    $chunkDir = "organizations/{$this->org->id}/audio/chunks/{$sessionId}";
    expect(Storage::disk('local')->files($chunkDir))->toHaveCount(3);

    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.audio-chunks.finalize', $this->meeting), [
            'session_id' => $sessionId,
            'mime_type' => 'audio/webm',
            'duration_seconds' => 420,
            'language' => 'en',
        ]);

    $response->assertOk();
    $response->assertJsonFragment(['message' => 'Recording finalized and transcription started.']);

    $transcription = AudioTranscription::query()
        ->where('minutes_of_meeting_id', $this->meeting->id)
        ->first();

    expect($transcription)->not->toBeNull();
    expect($transcription->duration_seconds)->toBe(420);
    expect($transcription->language)->toBe('en');
    expect($transcription->mime_type)->toBe('audio/webm');

    $input = $this->meeting->inputs()
        ->where('source_type', AudioTranscription::class)
        ->where('source_id', $transcription->id)
        ->first();

    expect($input)->not->toBeNull();
    expect($input->type)->toBe(InputType::BrowserRecording);

    Queue::assertPushed(ProcessTranscriptionJob::class);
});

it('cleans up chunks on cancel', function () {
    $sessionId = fake()->uuid();
    $chunk = UploadedFile::fake()->create('chunk.webm', 100, 'audio/webm');

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

    $response->assertOk();
    $response->assertJson(['message' => 'Chunks deleted.']);

    expect(Storage::disk('local')->files($chunkDir))->toBeEmpty();

    $this->assertDatabaseMissing('audio_transcriptions', [
        'minutes_of_meeting_id' => $this->meeting->id,
    ]);
});
