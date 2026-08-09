<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // The null broadcaster skips channel authorisation entirely, so these tests
    // would pass without proving anything. Reverb runs the real check; the
    // credentials only have to sign a response, never reach a server.
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'broadcasting.connections.reverb.options.host' => 'localhost',
    ]);

    // routes/channels.php was already evaluated against the previous default
    // connection when the app booted, so the callbacks have to be registered
    // again now that reverb is the default or the broadcaster knows no channels.
    require base_path('routes/channels.php');

    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    $this->meeting = MinutesOfMeeting::createForOrganization($this->org->id, [
        'title' => 'Board meeting',
        'created_by' => $this->user->id,
    ]);

    $this->session = LiveMeetingSession::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'started_by' => $this->user->id,
        'status' => LiveSessionStatus::Active,
    ]);
});

test('channel auth is reachable with a bearer token rather than a session cookie', function () {
    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/mobile/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-live-meeting.{$this->session->id}",
        ]);

    $response->assertOk();
    expect($response->json('auth'))->toBeString();
});

test('a live session in another organization is refused', function () {
    $otherOrg = Organization::factory()->create();
    $outsider = User::factory()->create(['current_organization_id' => $otherOrg->id]);
    $otherOrg->members()->attach($outsider, ['role' => UserRole::Owner->value]);

    $this->actingAs($outsider, 'sanctum')
        ->postJson('/api/mobile/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-live-meeting.{$this->session->id}",
        ])
        ->assertStatus(403);
});

test('channel auth requires authentication', function () {
    $this->postJson('/api/mobile/v1/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => "private-live-meeting.{$this->session->id}",
    ])->assertStatus(401);
});
