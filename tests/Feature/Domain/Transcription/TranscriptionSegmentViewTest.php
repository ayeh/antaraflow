<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Models\TranscriptionSegment;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('transcription show page displays speaker name for each segment', function () {
    $transcription = AudioTranscription::factory()->completed()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
    ]);

    TranscriptionSegment::factory()->create([
        'audio_transcription_id' => $transcription->id,
        'speaker' => 'Speaker 1',
        'start_time' => 0.0,
        'end_time' => 5.0,
        'confidence' => 0.90,
        'is_edited' => false,
    ]);

    TranscriptionSegment::factory()->create([
        'audio_transcription_id' => $transcription->id,
        'speaker' => 'Speaker 2',
        'start_time' => 5.0,
        'end_time' => 10.0,
        'confidence' => 0.85,
        'is_edited' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.transcriptions.show', [$this->meeting, $transcription]));

    $response->assertSuccessful();
    $response->assertSee('Speaker 1');
    $response->assertSee('Speaker 2');
});

test('transcription show page displays timestamps in mm:ss format', function () {
    $transcription = AudioTranscription::factory()->completed()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
    ]);

    TranscriptionSegment::factory()->create([
        'audio_transcription_id' => $transcription->id,
        'start_time' => 65.0,
        'end_time' => 70.5,
        'confidence' => null,
        'is_edited' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.transcriptions.show', [$this->meeting, $transcription]));

    $response->assertSuccessful();
    $response->assertSee('01:05');
    $response->assertSee('01:10');
});

test('transcription show page displays confidence percentage for segments with confidence', function () {
    $transcription = AudioTranscription::factory()->completed()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
    ]);

    TranscriptionSegment::factory()->create([
        'audio_transcription_id' => $transcription->id,
        'start_time' => 0.0,
        'end_time' => 5.0,
        'confidence' => 0.95,
        'is_edited' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.transcriptions.show', [$this->meeting, $transcription]));

    $response->assertSuccessful();
    $response->assertSee('95.0%');
});

test('transcription show page displays Edited badge for is_edited segments', function () {
    $transcription = AudioTranscription::factory()->completed()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
    ]);

    TranscriptionSegment::factory()->edited()->create([
        'audio_transcription_id' => $transcription->id,
        'start_time' => 0.0,
        'end_time' => 5.0,
        'confidence' => 0.80,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.transcriptions.show', [$this->meeting, $transcription]));

    $response->assertSuccessful();
    $response->assertSee('Edited');
});

test('segments without speaker or confidence display gracefully', function () {
    $transcription = AudioTranscription::factory()->completed()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
    ]);

    $segment = TranscriptionSegment::factory()->create([
        'audio_transcription_id' => $transcription->id,
        'speaker' => null,
        'start_time' => 10.0,
        'end_time' => 20.0,
        'confidence' => null,
        'is_edited' => false,
        'text' => 'A segment with no speaker or confidence.',
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.transcriptions.show', [$this->meeting, $transcription]));

    $response->assertSuccessful();
    $response->assertSee('A segment with no speaker or confidence.');
    $response->assertSee('00:10');
    $response->assertSee('00:20');
    $response->assertDontSee('Edited');
});
