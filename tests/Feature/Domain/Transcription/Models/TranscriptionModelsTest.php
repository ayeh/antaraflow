<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomInput;
use App\Domain\Meeting\Models\MomManualNote;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Models\TranscriptionSegment;
use App\Models\User;
use App\Support\Enums\InputType;
use App\Support\Enums\TranscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('audio transcription belongs to meeting', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $transcription = AudioTranscription::factory()->create([
        'minutes_of_meeting_id' => $mom->id,
        'uploaded_by' => $user->id,
    ]);

    expect($transcription->minutesOfMeeting->id)->toBe($mom->id);
});

test('audio transcription has many segments', function () {
    $transcription = AudioTranscription::factory()->create();

    TranscriptionSegment::factory()->count(3)->create([
        'audio_transcription_id' => $transcription->id,
    ]);

    expect($transcription->segments)->toHaveCount(3);
});

test('transcription segment belongs to transcription', function () {
    $transcription = AudioTranscription::factory()->create();

    $segment = TranscriptionSegment::factory()->create([
        'audio_transcription_id' => $transcription->id,
    ]);

    expect($segment->audioTranscription->id)->toBe($transcription->id);
});

test('mom input morphs to source', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $transcription = AudioTranscription::factory()->create([
        'minutes_of_meeting_id' => $mom->id,
        'uploaded_by' => $user->id,
    ]);

    $input = MomInput::factory()->create([
        'minutes_of_meeting_id' => $mom->id,
        'type' => InputType::Audio,
        'source_type' => AudioTranscription::class,
        'source_id' => $transcription->id,
    ]);

    expect($input->source)->toBeInstanceOf(AudioTranscription::class)
        ->and($input->source->id)->toBe($transcription->id);
});

test('manual note belongs to meeting', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $note = MomManualNote::factory()->create([
        'minutes_of_meeting_id' => $mom->id,
        'created_by' => $user->id,
    ]);

    expect($note->minutesOfMeeting->id)->toBe($mom->id)
        ->and($note->createdBy->id)->toBe($user->id);
});

test('audio transcription casts status to enum', function () {
    $transcription = AudioTranscription::factory()->create();

    expect($transcription->status)->toBeInstanceOf(TranscriptionStatus::class)
        ->and($transcription->status)->toBe(TranscriptionStatus::Pending);
});

test('transcription segment orders by sequence_order by default', function () {
    $transcription = AudioTranscription::factory()->create();

    TranscriptionSegment::factory()->create([
        'audio_transcription_id' => $transcription->id,
        'sequence_order' => 2,
    ]);
    TranscriptionSegment::factory()->create([
        'audio_transcription_id' => $transcription->id,
        'sequence_order' => 0,
    ]);
    TranscriptionSegment::factory()->create([
        'audio_transcription_id' => $transcription->id,
        'sequence_order' => 1,
    ]);

    $segments = $transcription->segments()->get();

    expect($segments[0]->sequence_order)->toBe(0)
        ->and($segments[1]->sequence_order)->toBe(1)
        ->and($segments[2]->sequence_order)->toBe(2);
});
