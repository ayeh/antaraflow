<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

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

test('user can upload audio to meeting', function () {
    Storage::fake('local');
    Queue::fake();

    $file = UploadedFile::fake()->create('meeting.mp3', 1024, 'audio/mpeg');

    $response = $this->actingAs($this->user)
        ->post(route('meetings.transcriptions.store', $this->meeting), [
            'audio' => $file,
            'language' => 'en',
        ]);

    $response->assertRedirect(route('meetings.show', $this->meeting));

    $this->assertDatabaseHas('audio_transcriptions', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
        'original_filename' => 'meeting.mp3',
    ]);
});

test('upload validates file type', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $response = $this->actingAs($this->user)
        ->post(route('meetings.transcriptions.store', $this->meeting), [
            'audio' => $file,
        ]);

    $response->assertSessionHasErrors('audio');
});

test('user can view transcription', function () {
    $transcription = AudioTranscription::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.transcriptions.show', [$this->meeting, $transcription]));

    $response->assertSuccessful();
});

test('user can delete transcription', function () {
    Storage::fake('local');

    $transcription = AudioTranscription::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
        'file_path' => 'organizations/1/audio/test.mp3',
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('meetings.transcriptions.destroy', [$this->meeting, $transcription]));

    $response->assertRedirect(route('meetings.show', $this->meeting));
    $this->assertDatabaseMissing('audio_transcriptions', ['id' => $transcription->id]);
});

test('store returns json response when accept header is json', function () {
    Storage::fake('local');
    Queue::fake();

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
        'original_filename' => 'recording.mp3',
    ]);
});

test('guest cannot upload audio', function () {
    $response = $this->post(route('meetings.transcriptions.store', $this->meeting), [
        'audio' => UploadedFile::fake()->create('meeting.mp3', 1024, 'audio/mpeg'),
    ]);

    $response->assertRedirect(route('login'));
});

it('transcription tab contains language selector', function (): void {
    $this->actingAs($this->user)
        ->get(route('meetings.show', $this->meeting))
        ->assertOk()
        ->assertSee('name="language"', false)
        ->assertSee('value="en"', false)
        ->assertSee('value="ms"', false);
});

/*
 * The picker offered .mp4 all along — macOS maps it to the same UTI as .m4a
 * under accept="audio/*" — while validation rejected it, so choosing a screen
 * or Zoom recording failed at the server for no reason the user could see.
 */
test('user can upload an mp4 recording', function () {
    Storage::fake('local');
    Queue::fake();

    $file = UploadedFile::fake()->create('meeting sistem emas.mp4', 1024, 'video/mp4');

    $this->actingAs($this->user)
        ->post(route('meetings.transcriptions.store', $this->meeting), [
            'audio' => $file,
            'language' => 'en',
        ])
        ->assertRedirect(route('meetings.show', $this->meeting))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('audio_transcriptions', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'original_filename' => 'meeting sistem emas.mp4',
        'mime_type' => 'video/mp4',
    ]);
});

test('a member may upload, not just an owner', function () {
    Storage::fake('local');
    Queue::fake();

    $member = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($member, ['role' => UserRole::Member->value]);

    $this->actingAs($member)
        ->post(route('meetings.transcriptions.store', $this->meeting), [
            'audio' => UploadedFile::fake()->create('rakaman.mp4', 512, 'video/mp4'),
        ])
        ->assertSessionHasNoErrors();
});

test('a genuine non-media file is still rejected', function () {
    Storage::fake('local');

    $this->actingAs($this->user)
        ->post(route('meetings.transcriptions.store', $this->meeting), [
            'audio' => UploadedFile::fake()->create('slides.pptx', 1024, 'application/vnd.openxmlformats-officedocument.presentationml.presentation'),
        ])
        ->assertSessionHasErrors('audio');
});
