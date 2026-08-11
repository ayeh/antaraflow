<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Models\User;
use App\Support\Enums\TranscriptionStatus;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Every read endpoint answered once, so a controller that cannot even be
 * constructed — a wrong service signature, a missing relation — fails here
 * rather than on a phone.
 */
beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->meeting = MinutesOfMeeting::createForOrganization($this->org->id, [
        'title' => 'Board meeting',
        'created_by' => $this->user->id,
        'meeting_date' => now(),
    ]);

    ActionItem::createForOrganization($this->org->id, [
        'minutes_of_meeting_id' => $this->meeting->id,
        'title' => 'Follow up',
        'created_by' => $this->user->id,
        'assigned_to' => $this->user->id,
    ]);

    $this->transcription = AudioTranscription::query()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
        'original_filename' => 'audio.m4a',
        'file_path' => 'audio.m4a',
        'mime_type' => 'audio/mp4',
        'file_size' => 100,
        'duration_seconds' => 60,
        'language' => 'en',
        'status' => TranscriptionStatus::Completed,
        'full_text' => 'Hello',
    ]);
});

dataset('read endpoints', function () {
    return [
        'bootstrap' => fn () => '/api/mobile/v1/bootstrap',
        'meetings' => fn () => '/api/mobile/v1/meetings',
        'meeting detail' => fn () => "/api/mobile/v1/meetings/{$this->meeting->id}",
        'meeting pack' => fn () => "/api/mobile/v1/meetings/{$this->meeting->id}/pack",
        'calendar' => fn () => '/api/mobile/v1/meetings/calendar?from='.now()->subMonth()->toDateString().'&to='.now()->addMonth()->toDateString(),
        'action items' => fn () => '/api/mobile/v1/action-items',
        'attendees' => fn () => "/api/mobile/v1/meetings/{$this->meeting->id}/attendees",
        'documents' => fn () => "/api/mobile/v1/meetings/{$this->meeting->id}/documents",
        'voice notes' => fn () => "/api/mobile/v1/meetings/{$this->meeting->id}/voice-notes",
        'comments' => fn () => "/api/mobile/v1/meetings/{$this->meeting->id}/comments",
        'resolutions' => fn () => "/api/mobile/v1/meetings/{$this->meeting->id}/resolutions",
        'exports' => fn () => "/api/mobile/v1/meetings/{$this->meeting->id}/exports",
        'extractions' => fn () => "/api/mobile/v1/meetings/{$this->meeting->id}/extractions",
        'chat history' => fn () => "/api/mobile/v1/meetings/{$this->meeting->id}/chat",
        'prep brief' => fn () => "/api/mobile/v1/meetings/{$this->meeting->id}/prep-brief",
        'transcriptions' => fn () => "/api/mobile/v1/meetings/{$this->meeting->id}/transcriptions",
        'transcription detail' => fn () => "/api/mobile/v1/transcriptions/{$this->transcription->id}",
        'transcription segments' => fn () => "/api/mobile/v1/transcriptions/{$this->transcription->id}/segments",
        'qr registration' => fn () => "/api/mobile/v1/meetings/{$this->meeting->id}/qr-registration",
        'circulations pending' => fn () => '/api/mobile/v1/circulations/pending',
        'insights' => fn () => '/api/mobile/v1/insights',
        'notifications' => fn () => '/api/mobile/v1/notifications',
        'unread count' => fn () => '/api/mobile/v1/notifications/unread-count',
        'settings profile' => fn () => '/api/mobile/v1/settings/profile',
        'settings notifications' => fn () => '/api/mobile/v1/settings/notifications',
        'search' => fn () => '/api/mobile/v1/search?q=board',
        'sync pull' => fn () => '/api/mobile/v1/sync/pull',
    ];
});

test('read endpoint answers successfully', function (string $url) {
    $this->actingAs($this->user, 'sanctum')->getJson($url)->assertSuccessful();
})->with('read endpoints');

test('every read endpoint refuses an unauthenticated caller', function (string $url) {
    $this->getJson($url)
        ->assertStatus(401)
        ->assertJsonPath('code', 'UNAUTHENTICATED');
})->with('read endpoints');
