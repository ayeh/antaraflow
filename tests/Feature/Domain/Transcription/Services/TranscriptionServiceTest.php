<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Jobs\ProcessTranscriptionJob;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Models\TranscriptionSegment;
use App\Domain\Transcription\Services\TranscriptionService;
use App\Models\User;
use App\Support\Enums\TranscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('can upload audio file and create transcription record', function () {
    Storage::fake('local');
    Queue::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);
    $file = UploadedFile::fake()->create('meeting.mp3', 1024, 'audio/mpeg');

    $service = app(TranscriptionService::class);
    $transcription = $service->upload($file, $mom, $user);

    expect($transcription)->toBeInstanceOf(AudioTranscription::class)
        ->and($transcription->status)->toBe(TranscriptionStatus::Pending)
        ->and($transcription->original_filename)->toBe('meeting.mp3')
        ->and($transcription->language)->toBe('en');

    $this->assertDatabaseHas('mom_inputs', [
        'minutes_of_meeting_id' => $mom->id,
        'source_type' => AudioTranscription::class,
    ]);
});

test('upload dispatches processing job', function () {
    Queue::fake();
    Storage::fake('local');

    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);
    $file = UploadedFile::fake()->create('meeting.mp3', 1024, 'audio/mpeg');

    $service = app(TranscriptionService::class);
    $service->upload($file, $mom, $user);

    Queue::assertPushed(ProcessTranscriptionJob::class);
});

test('can update transcription segment text', function () {
    $segment = TranscriptionSegment::factory()->create([
        'text' => 'Original text',
        'is_edited' => false,
    ]);

    $service = app(TranscriptionService::class);
    $updated = $service->updateSegment($segment, 'Updated text');

    expect($updated->text)->toBe('Updated text')
        ->and($updated->is_edited)->toBeTrue();
});

test('can get segments for a transcription', function () {
    $transcription = AudioTranscription::factory()->create();

    TranscriptionSegment::factory()->create([
        'audio_transcription_id' => $transcription->id,
        'sequence_order' => 1,
    ]);
    TranscriptionSegment::factory()->create([
        'audio_transcription_id' => $transcription->id,
        'sequence_order' => 0,
    ]);

    $service = app(TranscriptionService::class);
    $segments = $service->getSegments($transcription);

    expect($segments)->toHaveCount(2)
        ->and($segments->first()->sequence_order)->toBe(0);
});

test('upload stores file in organization directory', function () {
    Storage::fake('local');
    Queue::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);
    $file = UploadedFile::fake()->create('meeting.mp3', 1024, 'audio/mpeg');

    $service = app(TranscriptionService::class);
    $transcription = $service->upload($file, $mom, $user);

    Storage::disk('local')->assertExists($transcription->file_path);
    expect($transcription->file_path)->toContain("organizations/{$org->id}/audio");
});
