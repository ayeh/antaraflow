<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Services\LiveMeetingService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Note: the suite runs on SQLite, which never attached ON UPDATE
 * CURRENT_TIMESTAMP to these columns, so this cannot reproduce the MySQL
 * defect the accompanying migration fixes. It guards the application side —
 * that ending a session leaves the recorded start alone — and the schema fix
 * itself was verified directly against production MySQL.
 */
it('does not rewrite started_at when the session row is updated', function (): void {
    Queue::fake();

    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    $session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $meeting->id,
        'started_by' => $user->id,
        'started_at' => now()->subMinutes(45),
        'status' => LiveSessionStatus::Active,
    ]);

    $startedAt = $session->fresh()->started_at;

    app(LiveMeetingService::class)->endSession($session);

    $session->refresh();

    expect($session->started_at->timestamp)->toBe($startedAt->timestamp)
        ->and($session->started_at->lessThan($session->ended_at))->toBeTrue()
        ->and($session->total_duration_seconds)->toBeGreaterThan(2600);
});
