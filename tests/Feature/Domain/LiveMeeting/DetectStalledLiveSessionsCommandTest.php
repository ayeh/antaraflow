<?php

declare(strict_types=1);

use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Domain\LiveMeeting\Notifications\LiveRecordingStalledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/** Give a session one chunk whose upload landed the given number of minutes ago. */
function chunkAgedMinutes(LiveMeetingSession $session, int $minutes, int $number = 1): LiveTranscriptChunk
{
    return LiveTranscriptChunk::factory()->completed()->create([
        'live_meeting_session_id' => $session->id,
        'chunk_number' => $number,
        'created_at' => now()->subMinutes($minutes),
    ]);
}

test('alerts the starter when recording has been silent past the alert window', function () {
    Notification::fake();
    Queue::fake();

    $session = LiveMeetingSession::factory()->create();
    chunkAgedMinutes($session, 5);

    $this->artisan('live:detect-stalled')->assertSuccessful();

    Notification::assertSentTo($session->startedBy, LiveRecordingStalledNotification::class);

    $session->refresh();
    expect($session->status)->toBe(LiveSessionStatus::Active)
        ->and($session->stall_notified_at)->not->toBeNull();
});

test('does not alert twice for the same stall', function () {
    Notification::fake();
    Queue::fake();

    $session = LiveMeetingSession::factory()->create();
    chunkAgedMinutes($session, 5);

    $this->artisan('live:detect-stalled')->assertSuccessful();
    $this->artisan('live:detect-stalled')->assertSuccessful();

    Notification::assertSentToTimes($session->startedBy, LiveRecordingStalledNotification::class, 1);
});

test('auto-finalises a session silent past the finalize window', function () {
    Notification::fake();
    Queue::fake();

    $session = LiveMeetingSession::factory()->create();
    chunkAgedMinutes($session, 15);

    $this->artisan('live:detect-stalled')->assertSuccessful();

    $session->refresh();
    expect($session->status)->toBe(LiveSessionStatus::Ended)
        ->and($session->ended_at)->not->toBeNull();
});

test('leaves a healthy session that is still sending chunks alone', function () {
    Notification::fake();
    Queue::fake();

    $session = LiveMeetingSession::factory()->create();
    chunkAgedMinutes($session, 0);

    $this->artisan('live:detect-stalled')->assertSuccessful();

    Notification::assertNothingSent();
    expect($session->fresh()->status)->toBe(LiveSessionStatus::Active);
});

test('clears the alert mark once chunks start flowing again', function () {
    Notification::fake();
    Queue::fake();

    $session = LiveMeetingSession::factory()->create(['stall_notified_at' => now()->subMinutes(4)]);
    chunkAgedMinutes($session, 0);

    $this->artisan('live:detect-stalled')->assertSuccessful();

    expect($session->fresh()->stall_notified_at)->toBeNull();
});

test('ignores a session that never recorded a chunk', function () {
    Notification::fake();
    Queue::fake();

    $session = LiveMeetingSession::factory()->create(['started_at' => now()->subMinutes(30)]);

    $this->artisan('live:detect-stalled')->assertSuccessful();

    Notification::assertNothingSent();
    expect($session->fresh()->status)->toBe(LiveSessionStatus::Active);
});

test('ignores a session the user deliberately paused', function () {
    Notification::fake();
    Queue::fake();

    $session = LiveMeetingSession::factory()->paused()->create();
    chunkAgedMinutes($session, 20);

    $this->artisan('live:detect-stalled')->assertSuccessful();

    Notification::assertNothingSent();
    expect($session->fresh()->status)->toBe(LiveSessionStatus::Paused);
});

test('finalises a paused session abandoned far past any real break', function () {
    Notification::fake();
    Queue::fake();

    $session = LiveMeetingSession::factory()->paused()->create();
    // Seven hours idle — past the six-hour paused window, but never nagged.
    chunkAgedMinutes($session, 7 * 60);

    $this->artisan('live:detect-stalled')->assertSuccessful();

    Notification::assertNothingSent();
    expect($session->fresh()->status)->toBe(LiveSessionStatus::Ended)
        ->and($session->fresh()->ended_at)->not->toBeNull();
});

test('the paused finalize window is configurable', function () {
    Notification::fake();
    Queue::fake();

    $session = LiveMeetingSession::factory()->paused()->create();
    chunkAgedMinutes($session, 20);

    $this->artisan('live:detect-stalled --finalize-paused-after=15')->assertSuccessful();

    expect($session->fresh()->status)->toBe(LiveSessionStatus::Ended);
});

test('the alert window is configurable', function () {
    Notification::fake();
    Queue::fake();

    $session = LiveMeetingSession::factory()->create();
    chunkAgedMinutes($session, 2);

    // Default alert-after is 3 minutes, so 2 minutes idle would normally be quiet.
    $this->artisan('live:detect-stalled --alert-after=1')->assertSuccessful();

    Notification::assertSentTo($session->startedBy, LiveRecordingStalledNotification::class);
});
