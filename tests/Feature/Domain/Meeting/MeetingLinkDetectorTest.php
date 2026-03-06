<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Services\MeetingLinkDetector;
use App\Models\User;
use App\Support\Enums\MeetingPlatform;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Unit tests for MeetingLinkDetector

it('detects Zoom from url', function () {
    expect(MeetingLinkDetector::detect('https://zoom.us/j/123456789'))->toBe(MeetingPlatform::Zoom);
});

it('detects Google Meet from url', function () {
    expect(MeetingLinkDetector::detect('https://meet.google.com/abc-defg-hij'))->toBe(MeetingPlatform::GoogleMeet);
});

it('detects Microsoft Teams from teams.microsoft.com', function () {
    expect(MeetingLinkDetector::detect('https://teams.microsoft.com/l/meetup-join/abc123'))->toBe(MeetingPlatform::MicrosoftTeams);
});

it('detects Microsoft Teams from teams.live.com', function () {
    expect(MeetingLinkDetector::detect('https://teams.live.com/meet/abc123'))->toBe(MeetingPlatform::MicrosoftTeams);
});

it('returns Other for unknown urls', function () {
    expect(MeetingLinkDetector::detect('https://example.com/meeting/123'))->toBe(MeetingPlatform::Other);
});

it('returns null for null input', function () {
    expect(MeetingLinkDetector::detect(null))->toBeNull();
});

it('returns null for empty string', function () {
    expect(MeetingLinkDetector::detect(''))->toBeNull();
});

// Integration tests: platform auto-detection on meeting create/update

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

it('auto-detects platform when creating meeting with link', function () {
    $response = $this->actingAs($this->user)->post(route('meetings.store'), [
        'title' => 'Zoom Standup',
        'meeting_date' => now()->format('Y-m-d'),
        'prepared_by' => $this->user->name,
        'meeting_link' => 'https://zoom.us/j/999888777',
    ]);

    $meeting = MinutesOfMeeting::where('title', 'Zoom Standup')->first();
    expect($meeting)->not->toBeNull();
    expect($meeting->meeting_link)->toBe('https://zoom.us/j/999888777');
    expect($meeting->meeting_platform)->toBe(MeetingPlatform::Zoom);
});

it('auto-detects platform when updating meeting with link', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)->put(route('meetings.update', $meeting), [
        'title' => $meeting->title,
        'meeting_link' => 'https://meet.google.com/abc-defg-hij',
    ]);

    expect($meeting->fresh()->meeting_platform)->toBe(MeetingPlatform::GoogleMeet);
});

it('shows meeting link field on create page', function () {
    $response = $this->actingAs($this->user)->get(route('meetings.create'));

    $response->assertSuccessful();
    $response->assertSee('Meeting Link');
    $response->assertSee('meeting_link');
});

it('shows platform badge on meeting show page', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_link' => 'https://zoom.us/j/123',
        'meeting_platform' => MeetingPlatform::Zoom,
    ]);

    $response = $this->actingAs($this->user)->get(route('meetings.show', $meeting));

    $response->assertSuccessful();
    $response->assertSee('Zoom');
    $response->assertSee('Join Meeting');
    $response->assertSee('https://zoom.us/j/123');
});

it('does not show join meeting when no link', function () {
    $meeting = MinutesOfMeeting::factory()->draft()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_link' => null,
    ]);

    $response = $this->actingAs($this->user)->get(route('meetings.show', $meeting));

    $response->assertSuccessful();
    $response->assertDontSee('Join Meeting');
});
