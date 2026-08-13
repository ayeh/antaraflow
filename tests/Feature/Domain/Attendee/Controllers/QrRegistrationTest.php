<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Attendee\Models\QrRegistrationToken;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('generates QR registration token', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.qr-registration.generate', $this->meeting));

    $response->assertSuccessful();
    $response->assertJsonStructure(['token', 'url', 'expires_at']);
    $this->assertDatabaseHas('qr_registration_tokens', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'is_active' => true,
    ]);
});

test('generates QR registration token without an expiry and the link works', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('meetings.qr-registration.generate', $this->meeting));

    $response->assertSuccessful();
    $response->assertJson(['expires_at' => null]);

    $token = QrRegistrationToken::where('minutes_of_meeting_id', $this->meeting->id)->firstOrFail();

    $this->get(route('qr-registration.form', $token->token))
        ->assertSuccessful();
});

test('shows registration form with valid token', function () {
    $token = QrRegistrationToken::create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'token' => 'valid-test-token-123',
        'is_active' => true,
        'expires_at' => now()->addHours(24),
    ]);

    $response = $this->get(route('qr-registration.form', $token->token));

    $response->assertSuccessful();
    $response->assertSee($this->meeting->title);
});

test('allows public attendee registration with valid token', function () {
    $token = QrRegistrationToken::create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'token' => 'register-test-token-456',
        'is_active' => true,
        'expires_at' => now()->addHours(24),
    ]);

    $response = $this->post(route('qr-registration.submit', $token->token), [
        'name' => 'Walk-In Attendee',
        'email' => 'walkin@example.com',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('mom_attendees', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'name' => 'Walk-In Attendee',
        'email' => 'walkin@example.com',
        'is_present' => true,
        'is_external' => true,
    ]);
});

test('rejects registration with expired token', function () {
    $token = QrRegistrationToken::create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'token' => 'expired-test-token-789',
        'is_active' => true,
        'expires_at' => now()->subHour(),
    ]);

    $response = $this->get(route('qr-registration.form', $token->token));

    $response->assertStatus(410);
});

test('rejects registration with inactive token', function () {
    $token = QrRegistrationToken::create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'token' => 'inactive-test-token-000',
        'is_active' => false,
        'expires_at' => now()->addHours(24),
    ]);

    $response = $this->get(route('qr-registration.form', $token->token));

    $response->assertStatus(410);
});

test('shows registration form to a viewer from another organization', function () {
    $token = QrRegistrationToken::create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'token' => 'cross-org-token-111',
        'is_active' => true,
        'expires_at' => now()->addHours(24),
    ]);

    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['current_organization_id' => $otherOrg->id]);

    $response = $this->actingAs($otherUser)
        ->get(route('qr-registration.form', $token->token));

    $response->assertSuccessful();
    $response->assertSee($this->meeting->title);
});

test('allows registration from a viewer in another organization', function () {
    $token = QrRegistrationToken::create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'token' => 'cross-org-token-222',
        'is_active' => true,
        'expires_at' => now()->addHours(24),
    ]);

    $otherOrg = Organization::factory()->create();
    $otherUser = User::factory()->create(['current_organization_id' => $otherOrg->id]);

    $response = $this->actingAs($otherUser)
        ->post(route('qr-registration.submit', $token->token), [
            'name' => 'Cross Org Attendee',
            'email' => 'crossorg@example.com',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('mom_attendees', [
        'minutes_of_meeting_id' => $this->meeting->id,
        'name' => 'Cross Org Attendee',
    ]);
});

test('aborts 410 when the token meeting is soft-deleted', function () {
    $token = QrRegistrationToken::create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'token' => 'soft-deleted-token-333',
        'is_active' => true,
        'expires_at' => now()->addHours(24),
    ]);

    $this->meeting->delete();

    $response = $this->get(route('qr-registration.form', $token->token));

    $response->assertStatus(410);
});

test('returns live attendees and registration count for the lobby', function () {
    QrRegistrationToken::create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'token' => 'lobby-test-token-111',
        'is_active' => true,
        'expires_at' => now()->addHours(24),
        'max_attendees' => 5,
        'registrations_count' => 2,
    ]);

    MomAttendee::factory()->external()->present()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'name' => 'Ariff Walk-In',
        'company' => 'RocketWeb',
    ]);
    MomAttendee::factory()->external()->present()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'name' => 'Second Guest',
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('meetings.qr-registration.attendees', $this->meeting));

    $response->assertSuccessful();
    $response->assertJson([
        'is_active' => true,
        'registrations_count' => 2,
        'max_attendees' => 5,
    ]);
    $response->assertJsonCount(2, 'attendees');
    $response->assertJsonPath('attendees.0.name', 'Ariff Walk-In');
    $response->assertJsonPath('attendees.0.company', 'RocketWeb');
});

test('lobby excludes non-external attendees', function () {
    QrRegistrationToken::create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'token' => 'lobby-test-token-222',
        'is_active' => true,
    ]);

    MomAttendee::factory()->external()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'name' => 'External Guest',
    ]);
    MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'name' => 'Internal Member',
        'is_external' => false,
    ]);

    $response = $this->actingAs($this->user)
        ->getJson(route('meetings.qr-registration.attendees', $this->meeting));

    $response->assertSuccessful();
    $response->assertJsonCount(1, 'attendees');
    $response->assertJsonPath('attendees.0.name', 'External Guest');
});

test('lobby endpoint requires authentication', function () {
    $response = $this->getJson(route('meetings.qr-registration.attendees', $this->meeting));

    $response->assertUnauthorized();
});

test('shows the shareable live lobby screen publicly with a valid token', function () {
    $token = QrRegistrationToken::create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'token' => 'lobby-share-token-111',
        'join_code' => 'ABC123',
        'is_active' => true,
    ]);

    $response = $this->get(route('qr-registration.lobby', $token->token));

    $response->assertSuccessful();
    $response->assertSee($this->meeting->title);
    $response->assertSee('lobbyPage(');
});

test('shareable lobby attendees endpoint is public and returns live data', function () {
    $token = QrRegistrationToken::create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'token' => 'lobby-share-token-222',
        'is_active' => true,
        'max_attendees' => 5,
        'registrations_count' => 1,
    ]);

    MomAttendee::factory()->external()->present()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'name' => 'Public Guest',
        'company' => 'RocketWeb',
    ]);

    $response = $this->getJson(route('qr-registration.lobby.attendees', $token->token));

    $response->assertSuccessful();
    $response->assertJson([
        'is_active' => true,
        'registrations_count' => 1,
        'max_attendees' => 5,
    ]);
    $response->assertJsonCount(1, 'attendees');
    $response->assertJsonPath('attendees.0.name', 'Public Guest');
});

test('shareable lobby still renders when the token is inactive', function () {
    $token = QrRegistrationToken::create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'token' => 'lobby-share-token-333',
        'is_active' => false,
    ]);

    $this->get(route('qr-registration.lobby', $token->token))->assertSuccessful();
    $this->getJson(route('qr-registration.lobby.attendees', $token->token))
        ->assertSuccessful()
        ->assertJson(['is_active' => false]);
});

test('shareable lobby aborts 410 when the meeting is soft-deleted', function () {
    $token = QrRegistrationToken::create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'token' => 'lobby-share-token-444',
        'is_active' => true,
    ]);

    $this->meeting->delete();

    $this->get(route('qr-registration.lobby', $token->token))->assertStatus(410);
    $this->getJson(route('qr-registration.lobby.attendees', $token->token))->assertStatus(410);
});

test('shareable lobby returns 404 for an unknown token', function () {
    $this->get(route('qr-registration.lobby', 'does-not-exist'))->assertNotFound();
});

describe('guest rate limits', function () {
    beforeEach(function () {
        $this->token = $this->meeting->qrRegistrationTokens()->create([
            'token' => 'throttle-token-9001',
            'join_code' => 'THR001',
            'is_active' => true,
            'required_fields' => ['name'],
            'registrations_count' => 0,
        ]);
    });

    test('a lobby left polling does not lock walk-ins out of registration', function () {
        // What a projector does in the first minute of an event.
        for ($i = 0; $i < 25; $i++) {
            $this->get(route('qr-registration.lobby.attendees', $this->token->token))
                ->assertOk();
        }

        // Somebody then walks in and scans. Before the groups were given
        // separate prefixes this was a 429: one shared domain|ip counter meant
        // the lobby had already spent the registration allowance.
        $this->get(route('qr-registration.form', $this->token->token))
            ->assertOk();
    });

    test('a room registering from one wifi is not turned away', function () {
        // Ten people, two requests each, all behind the same NAT address.
        for ($i = 0; $i < 10; $i++) {
            $this->get(route('qr-registration.form', $this->token->token))
                ->assertOk();

            $this->post(route('qr-registration.submit', $this->token->token), [
                'name' => "Guest $i",
                'email' => "guest$i@example.com",
            ])->assertRedirect();
        }

        expect($this->meeting->attendees()->count())->toBe(10);
    });
});
