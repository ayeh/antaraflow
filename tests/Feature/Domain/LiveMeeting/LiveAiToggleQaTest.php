<?php

declare(strict_types=1);

/**
 * QA/QC — Live AI on/off toggle
 *
 * Covers gaps not addressed by the existing controller, scheduling, and job
 * tests: authentication, authorization, HTTP edge cases, named queue,
 * mid-session toggle effect, cache isolation, and the global AI kill-switch.
 */

use App\Domain\Account\Models\Organization;
use App\Domain\Admin\Services\AiControlService;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Events\TranscriptionChunkProcessed;
use App\Domain\LiveMeeting\Jobs\LiveExtractionJob;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\Exceptions\AiDisabledException;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->owner = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->owner, ['role' => UserRole::Manager->value]);

    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->owner->id,
    ]);

    $this->session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->owner->id,
        'status' => LiveSessionStatus::Active,
        'config' => ['chunk_interval' => 30, 'extraction_interval' => 300, 'live_extraction' => false],
    ]);
});

// ── Authentication ───────────────────────────────────────────────────────────

it('returns 401 when an unauthenticated request tries to toggle', function () {
    $this->postJson(route('meetings.live.extraction', [$this->meeting, $this->session]), ['enabled' => true])
        ->assertUnauthorized();

    expect($this->session->fresh()->config['live_extraction'])->toBeFalse();
});

// ── Authorization ────────────────────────────────────────────────────────────

it('returns 403 when a viewer tries to toggle live extraction', function () {
    $viewer = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($viewer, ['role' => UserRole::Viewer->value]);

    $this->actingAs($viewer)
        ->postJson(route('meetings.live.extraction', [$this->meeting, $this->session]), ['enabled' => true])
        ->assertForbidden();

    expect($this->session->fresh()->config['live_extraction'])->toBeFalse();
});

// ── HTTP edge cases ──────────────────────────────────────────────────────────

it('returns 404 when the session belongs to a different meeting', function () {
    $otherMeeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->postJson(route('meetings.live.extraction', [$otherMeeting, $this->session]), ['enabled' => true])
        ->assertNotFound();
});

it('returns 409 when toggling on a paused session', function () {
    $this->session->update(['status' => LiveSessionStatus::Paused]);

    $this->actingAs($this->owner)
        ->postJson(route('meetings.live.extraction', [$this->meeting, $this->session]), ['enabled' => true])
        ->assertConflict();

    expect($this->session->fresh()->config['live_extraction'])->toBeFalse();
});

// ── Response messages ────────────────────────────────────────────────────────

it('responds with a message that confirms extraction is on', function () {
    $this->actingAs($this->owner)
        ->postJson(route('meetings.live.extraction', [$this->meeting, $this->session]), ['enabled' => true])
        ->assertOk()
        ->assertJsonPath('message', 'Live AI extraction is on. It will update every few minutes.');
});

it('responds with a message that confirms extraction is off', function () {
    $this->session->update(['config' => [...$this->session->config, 'live_extraction' => true]]);

    $this->actingAs($this->owner)
        ->postJson(route('meetings.live.extraction', [$this->meeting, $this->session]), ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('message', 'Live AI extraction is off.');
});

// ── Session start with live_extraction in initial config ─────────────────────

it('starts a session with live_extraction enabled when requested at start', function () {
    $freshMeeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->postJson(route('meetings.live.start', $freshMeeting), ['live_extraction' => true])
        ->assertCreated();

    $session = LiveMeetingSession::query()->latest('id')->first();
    expect($session->config['live_extraction'])->toBeTrue();
});

// ── Dashboard view reflects the toggle state ─────────────────────────────────

it('dashboard passes live_extraction true into the view when session has it on', function () {
    $this->session->update(['config' => [...$this->session->config, 'live_extraction' => true]]);

    $response = $this->actingAs($this->owner)
        ->get(route('meetings.live.show', [$this->meeting, $this->session]));

    $response->assertOk();
    // The blade renders: liveExtraction: true  — verify the JS literal is present.
    $response->assertSee('liveExtraction: true', false);
});

it('dashboard passes live_extraction false into the view when session has it off', function () {
    $response = $this->actingAs($this->owner)
        ->get(route('meetings.live.show', [$this->meeting, $this->session]));

    $response->assertOk();
    $response->assertSee('liveExtraction: false', false);
});

// ── Mid-session toggle effect on scheduling ──────────────────────────────────

it('stops dispatching extraction once toggled off mid-session', function () {
    Queue::fake();
    Cache::forget("live-extraction:{$this->session->id}");

    // First enable it so a chunk fires the job.
    $this->session->update(['config' => [...$this->session->config, 'live_extraction' => true]]);

    $chunk = LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $this->session->id,
        'chunk_number' => 1,
        'text' => 'Some speech.',
    ]);

    event(new TranscriptionChunkProcessed($chunk));
    Queue::assertPushed(LiveExtractionJob::class, 1);

    // Now disable it.
    Queue::fake();
    Cache::forget("live-extraction:{$this->session->id}");
    $this->session->update(['config' => [...$this->session->fresh()->config, 'live_extraction' => false]]);

    event(new TranscriptionChunkProcessed($chunk->fresh()));
    Queue::assertNotPushed(LiveExtractionJob::class);
});

it('resumes dispatching extraction once toggled back on', function () {
    Queue::fake();
    Cache::forget("live-extraction:{$this->session->id}");

    // Start off, confirm silence.
    $chunk = LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $this->session->id,
        'chunk_number' => 1,
        'text' => 'Some speech.',
    ]);

    event(new TranscriptionChunkProcessed($chunk));
    Queue::assertNotPushed(LiveExtractionJob::class);

    // Enable and fire again.
    Queue::fake();
    Cache::forget("live-extraction:{$this->session->id}");
    $this->session->update(['config' => [...$this->session->fresh()->config, 'live_extraction' => true]]);

    event(new TranscriptionChunkProcessed($chunk->fresh()));
    Queue::assertPushed(LiveExtractionJob::class, 1);
});

// ── Cache isolation across sessions ─────────────────────────────────────────

it('gives each session its own extraction window independent of other sessions', function () {
    Queue::fake();

    $otherMeeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->owner->id,
    ]);
    $otherSession = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $otherMeeting->id,
        'started_by' => $this->owner->id,
        'status' => LiveSessionStatus::Active,
        'config' => ['live_extraction' => true, 'extraction_interval' => 300],
    ]);

    $this->session->update(['config' => [...$this->session->config, 'live_extraction' => true]]);

    Cache::forget("live-extraction:{$this->session->id}");
    Cache::forget("live-extraction:{$otherSession->id}");

    $chunkA = LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $this->session->id,
        'chunk_number' => 1,
        'text' => 'Meeting one.',
    ]);
    $chunkB = LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $otherSession->id,
        'chunk_number' => 1,
        'text' => 'Meeting two.',
    ]);

    event(new TranscriptionChunkProcessed($chunkA));
    event(new TranscriptionChunkProcessed($chunkB));

    // Each session fires its own job — 2 total.
    Queue::assertPushed(LiveExtractionJob::class, 2);
});

// ── Named queue ──────────────────────────────────────────────────────────────

it('dispatches the extraction job onto the live-extraction queue', function () {
    Queue::fake();
    Cache::forget("live-extraction:{$this->session->id}");

    $this->session->update(['config' => [...$this->session->config, 'live_extraction' => true]]);

    $chunk = LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $this->session->id,
        'chunk_number' => 1,
        'text' => 'Hello.',
    ]);

    event(new TranscriptionChunkProcessed($chunk));

    Queue::assertPushedOn('live-extraction', LiveExtractionJob::class);
});

// ── Global AI kill-switch ────────────────────────────────────────────────────

it('live extraction job throws AiDisabledException when global AI is off', function () {
    app(AiControlService::class)->disable();

    LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $this->session->id,
        'chunk_number' => 1,
        'text' => 'Speech text.',
    ]);

    expect(fn () => (new LiveExtractionJob($this->session))->handle())
        ->toThrow(AiDisabledException::class);
});

it('scheduling listener still queues the job when AI is globally off — the job itself refuses', function () {
    Queue::fake();
    Cache::forget("live-extraction:{$this->session->id}");

    $this->session->update(['config' => [...$this->session->config, 'live_extraction' => true]]);
    app(AiControlService::class)->disable();

    $chunk = LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $this->session->id,
        'chunk_number' => 1,
        'text' => 'Some speech.',
    ]);

    event(new TranscriptionChunkProcessed($chunk));

    // The listener enqueues regardless — the worker is the one that hits the guard.
    Queue::assertPushed(LiveExtractionJob::class);
});
