<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
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
