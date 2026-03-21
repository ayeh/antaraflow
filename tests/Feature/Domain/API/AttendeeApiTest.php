<?php

declare(strict_types=1);

use App\Domain\Account\Models\ApiKey;
use App\Domain\Account\Models\Organization;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->orgOwner = User::factory()->create();
    $this->org->members()->attach($this->orgOwner->id, ['role' => 'owner']);

    $rawToken = 'test-api-key-'.uniqid();
    $this->apiKey = ApiKey::factory()->create([
        'organization_id' => $this->org->id,
        'secret_hash' => hash('sha256', $rawToken),
        'is_active' => true,
        'expires_at' => null,
    ]);
    $this->headers = ['Authorization' => 'Bearer '.$rawToken];
    $this->meeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);
});

it('GET /api/v1/meetings/{meeting}/attendees returns attendees for the meeting', function (): void {
    MomAttendee::factory()->count(3)->create(['minutes_of_meeting_id' => $this->meeting->id]);

    $this->getJson("/api/v1/meetings/{$this->meeting->id}/attendees", $this->headers)
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('GET /api/v1/meetings/{meeting}/attendees returns 404 for meeting in different org', function (): void {
    $otherMeeting = MinutesOfMeeting::factory()->create(); // different org

    $this->getJson("/api/v1/meetings/{$otherMeeting->id}/attendees", $this->headers)
        ->assertNotFound();
});

it('GET /api/v1/meetings/{meeting}/attendees requires auth', function (): void {
    $this->getJson("/api/v1/meetings/{$this->meeting->id}/attendees")
        ->assertUnauthorized();
});

it('GET /api/v1/meetings/{meeting}/attendees returns correct structure', function (): void {
    MomAttendee::factory()->create(['minutes_of_meeting_id' => $this->meeting->id]);

    $this->getJson("/api/v1/meetings/{$this->meeting->id}/attendees", $this->headers)
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'role', 'rsvp_status', 'is_present', 'is_external'],
            ],
        ]);
});

it('GET /api/v1/meetings/{meeting}/attendees only returns attendees for that meeting', function (): void {
    MomAttendee::factory()->count(2)->create(['minutes_of_meeting_id' => $this->meeting->id]);
    $otherMeeting = MinutesOfMeeting::factory()->create(['organization_id' => $this->org->id]);
    MomAttendee::factory()->count(3)->create(['minutes_of_meeting_id' => $otherMeeting->id]);

    $this->getJson("/api/v1/meetings/{$this->meeting->id}/attendees", $this->headers)
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
