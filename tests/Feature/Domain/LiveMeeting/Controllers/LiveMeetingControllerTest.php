<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\Meeting\Models\MinutesOfMeeting;
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
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);

    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

test('it can start a live session', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.live.start', $this->meeting));

    $response->assertCreated();
    $response->assertJsonStructure(['session' => ['id', 'status', 'started_at']]);

    $this->assertDatabaseHas('live_meeting_sessions', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active->value,
    ]);
});

test('it can start a live session with custom config', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.live.start', $this->meeting), [
            'chunk_interval' => 60,
            'extraction_interval' => 120,
        ]);

    $response->assertCreated();

    $session = LiveMeetingSession::query()->latest('id')->first();
    expect($session->config)->toBe(['chunk_interval' => 60, 'extraction_interval' => 120]);
});

test('it validates config values when starting a session', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.live.start', $this->meeting), [
            'chunk_interval' => 5,
            'extraction_interval' => 10,
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['chunk_interval', 'extraction_interval']);
});

test('it returns 409 when session already active', function () {
    LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.live.start', $this->meeting));

    $response->assertConflict();
    $response->assertJsonStructure(['error']);
});

test('it can show the live dashboard', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('meetings.live.show', [$this->meeting, $session]));

    $response->assertOk();
    $response->assertViewIs('meetings.live-dashboard');
    $response->assertViewHas('meeting');
    $response->assertViewHas('session');
    $response->assertViewHas('state');
});

test('it can upload an audio chunk', function () {
    Queue::fake();
    Storage::fake('local');

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
    ]);

    $audioFile = UploadedFile::fake()->create('chunk.webm', 100, 'audio/webm');

    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.live.chunk', [$this->meeting, $session]), [
            'audio' => $audioFile,
            'chunk_number' => 0,
            'start_time' => 0.0,
            'end_time' => 30.0,
        ]);

    $response->assertCreated();
    $response->assertJsonStructure(['chunk' => ['id', 'chunk_number', 'status']]);

    $this->assertDatabaseHas('live_transcript_chunks', [
        'live_meeting_session_id' => $session->id,
        'chunk_number' => 0,
    ]);
});

test('it validates chunk upload data', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.live.chunk', [$this->meeting, $session]), []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['audio', 'chunk_number', 'start_time', 'end_time']);
});

test('it validates end_time must be greater than start_time', function () {
    Storage::fake('local');

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
    ]);

    $audioFile = UploadedFile::fake()->create('chunk.webm', 100, 'audio/webm');

    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.live.chunk', [$this->meeting, $session]), [
            'audio' => $audioFile,
            'chunk_number' => 0,
            'start_time' => 30.0,
            'end_time' => 10.0,
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['end_time']);
});

test('it can end a live session', function () {
    Queue::fake();

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.live.end', [$this->meeting, $session]));

    $response->assertOk();
    $response->assertJson(['message' => 'Session ended successfully.']);

    $session->refresh();
    expect($session->status)->toBe(LiveSessionStatus::Ended);
    expect($session->ended_at)->not->toBeNull();
});

test('it can get session state as JSON', function () {
    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('meetings.live.state', [$this->meeting, $session]));

    $response->assertOk();
    $response->assertJsonStructure(['session', 'chunks', 'extractions']);
});

test('it returns 404 when session belongs to a different meeting', function () {
    $otherMeeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $otherMeeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);

    $this->actingAs($this->user)
        ->get(route('meetings.live.show', [$this->meeting, $session]))
        ->assertNotFound();

    $this->actingAs($this->user)
        ->postJson(route('meetings.live.end', [$this->meeting, $session]))
        ->assertNotFound();

    $this->actingAs($this->user)
        ->getJson(route('meetings.live.state', [$this->meeting, $session]))
        ->assertNotFound();
});

test('it returns 409 when chunking or ending a non-active session', function () {
    Queue::fake();
    Storage::fake('local');

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Ended,
    ]);

    $audioFile = UploadedFile::fake()->create('chunk.webm', 100, 'audio/webm');

    $this->actingAs($this->user)
        ->postJson(route('meetings.live.chunk', [$this->meeting, $session]), [
            'audio' => $audioFile,
            'chunk_number' => 0,
            'start_time' => 0.0,
            'end_time' => 30.0,
        ])
        ->assertConflict();

    $this->actingAs($this->user)
        ->postJson(route('meetings.live.end', [$this->meeting, $session]))
        ->assertConflict();
});

test('it requires authentication', function () {
    $response = $this->postJson(route('meetings.live.start', $this->meeting));

    $response->assertUnauthorized();
});

test('it requires authorization on meeting', function () {
    $viewerUser = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($viewerUser, ['role' => UserRole::Viewer->value]);

    $response = $this->actingAs($viewerUser)
        ->postJson(route('meetings.live.start', $this->meeting));

    $response->assertForbidden();
});
