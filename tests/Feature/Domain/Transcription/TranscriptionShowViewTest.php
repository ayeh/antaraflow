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

it('shows speaker timeline section when segments have speakers and duration is set', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);

    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $transcription = AudioTranscription::factory()->completed()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'uploaded_by' => $user->id,
        'duration_seconds' => 100,
    ]);

    TranscriptionSegment::factory()->create([
        'audio_transcription_id' => $transcription->id,
        'speaker' => 'Speaker 1',
        'start_time' => 0,
        'end_time' => 40,
        'sequence_order' => 0,
    ]);

    TranscriptionSegment::factory()->create([
        'audio_transcription_id' => $transcription->id,
        'speaker' => 'Speaker 2',
        'start_time' => 42,
        'end_time' => 100,
        'sequence_order' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('meetings.transcriptions.show', [$meeting, $transcription]))
        ->assertOk()
        ->assertSee('Speaker 1')
        ->assertSee('Speaker 2')
        ->assertSee('speaker-timeline', false);
});
