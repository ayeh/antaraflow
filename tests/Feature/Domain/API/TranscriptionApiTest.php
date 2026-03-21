<?php

declare(strict_types=1);

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Models\TranscriptionSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->orgOwner = User::factory()->create();
    $this->org->members()->attach($this->orgOwner->id, ['role' => 'owner']);

    $rawToken = 'test-api-key-'.uniqid();
    $this->apiKey = ApiKey::factory()->create([
        'organization_id' => $this->org->id,
        'secret_hash' => hash('sha256', $rawToken),
        'is_active' => true,
        'expires_at' => null,
    ]);
    $this->headers = ['Authorization' => 'Bearer '.$rawToken];
    $this->meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);
});

it('GET /api/v1/meetings/{meeting}/transcriptions returns transcriptions for the meeting', function (): void {
    AudioTranscription::factory()->count(2)->create(['minutes_of_meeting_id' => $this->meeting->id]);

    $this->getJson("/api/v1/meetings/{$this->meeting->id}/transcriptions", $this->headers)
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('GET /api/v1/meetings/{meeting}/transcriptions returns 404 for meeting in different org', function (): void {
    $otherMeeting = MinutesOfMeeting::factory()->create(); // different org

    $this->getJson("/api/v1/meetings/{$otherMeeting->id}/transcriptions", $this->headers)
        ->assertNotFound();
});

it('GET /api/v1/meetings/{meeting}/transcriptions requires auth', function (): void {
    $this->getJson("/api/v1/meetings/{$this->meeting->id}/transcriptions")
        ->assertUnauthorized();
});

it('GET /api/v1/meetings/{meeting}/transcriptions returns correct structure', function (): void {
    AudioTranscription::factory()->create(['minutes_of_meeting_id' => $this->meeting->id]);

    $this->getJson("/api/v1/meetings/{$this->meeting->id}/transcriptions", $this->headers)
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'language', 'status', 'duration_seconds', 'created_at'],
            ],
        ]);
});

it('GET /api/v1/meetings/{meeting}/transcriptions/{transcription} returns a single transcription with segments', function (): void {
    $transcription = AudioTranscription::factory()->completed()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
    ]);
    TranscriptionSegment::factory()->count(3)->create(['audio_transcription_id' => $transcription->id]);

    $this->getJson("/api/v1/meetings/{$this->meeting->id}/transcriptions/{$transcription->id}", $this->headers)
        ->assertOk()
        ->assertJsonStructure(['id', 'language', 'status', 'duration_seconds', 'created_at', 'segments'])
        ->assertJsonCount(3, 'segments');
});

it('GET /api/v1/meetings/{meeting}/transcriptions/{transcription} returns 404 for meeting in different org', function (): void {
    $otherMeeting = MinutesOfMeeting::factory()->create(); // different org
    $transcription = AudioTranscription::factory()->create(['minutes_of_meeting_id' => $otherMeeting->id]);

    $this->getJson("/api/v1/meetings/{$otherMeeting->id}/transcriptions/{$transcription->id}", $this->headers)
        ->assertNotFound();
});

it('GET /api/v1/meetings/{meeting}/transcriptions/{transcription} returns 404 when transcription does not belong to meeting', function (): void {
    $otherMeeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);
    $transcription = AudioTranscription::factory()->create(['minutes_of_meeting_id' => $otherMeeting->id]);

    $this->getJson("/api/v1/meetings/{$this->meeting->id}/transcriptions/{$transcription->id}", $this->headers)
        ->assertNotFound();
});
