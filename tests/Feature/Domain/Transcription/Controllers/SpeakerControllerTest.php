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

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
    $this->transcription = AudioTranscription::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
    ]);
});

it('renames all matching speaker labels in a transcription', function (): void {
    TranscriptionSegment::factory()->create(['audio_transcription_id' => $this->transcription->id, 'speaker' => 'Speaker 1', 'sequence_order' => 0]);
    TranscriptionSegment::factory()->create(['audio_transcription_id' => $this->transcription->id, 'speaker' => 'Speaker 1', 'sequence_order' => 1]);
    TranscriptionSegment::factory()->create(['audio_transcription_id' => $this->transcription->id, 'speaker' => 'Speaker 2', 'sequence_order' => 2]);

    $this->actingAs($this->user)
        ->patchJson(
            route('meetings.transcriptions.speakers.update', [$this->meeting, $this->transcription]),
            ['old_speaker' => 'Speaker 1', 'new_speaker' => 'Ahmad']
        )
        ->assertOk();

    expect(TranscriptionSegment::where('speaker', 'Ahmad')->count())->toBe(2);
    expect(TranscriptionSegment::where('speaker', 'Speaker 2')->count())->toBe(1);
    expect(TranscriptionSegment::where('speaker', 'Speaker 1')->count())->toBe(0);
});

it('returns 422 when old_speaker is missing', function (): void {
    $this->actingAs($this->user)
        ->patchJson(route('meetings.transcriptions.speakers.update', [$this->meeting, $this->transcription]), [])
        ->assertUnprocessable();
});

it('requires authentication to rename speakers', function (): void {
    $this->patchJson(
        route('meetings.transcriptions.speakers.update', [$this->meeting, $this->transcription]),
        ['old_speaker' => 'Speaker 1', 'new_speaker' => 'Ahmad']
    )->assertUnauthorized();
});
