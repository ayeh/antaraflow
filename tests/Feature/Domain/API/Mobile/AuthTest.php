<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\UserDevice;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create([
        'email' => 'ariff@example.com',
        'password' => 'Password123',
        'current_organization_id' => $this->org->id,
    ]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
});

/** @return array<string, string> */
function devicePayload(array $overrides = []): array
{
    return array_merge([
        'device_name' => 'iPhone 15 Pro',
        'device_id' => 'device-abc-123',
        'platform' => 'ios',
    ], $overrides);
}

test('login returns a token, profile and organization list', function () {
    $response = $this->postJson('/api/mobile/v1/auth/login', devicePayload([
        'email' => 'ariff@example.com',
        'password' => 'Password123',
    ]));

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'expires_at',
            'user' => ['id', 'name', 'email', 'locale'],
            'organizations' => [['id', 'name', 'role', 'is_current']],
            'abilities',
        ])
        ->assertJsonPath('user.email', 'ariff@example.com')
        ->assertJsonPath('organizations.0.is_current', true);
});

test('login registers the device for push', function () {
    $this->postJson('/api/mobile/v1/auth/login', devicePayload([
        'email' => 'ariff@example.com',
        'password' => 'Password123',
        'push_token' => 'fcm-token-xyz',
    ]))->assertOk();

    expect(UserDevice::query()->where('device_id', 'device-abc-123')->first())
        ->not->toBeNull()
        ->push_token->toBe('fcm-token-xyz')
        ->user_id->toBe($this->user->id);
});

test('login rejects wrong credentials with a stable code', function () {
    $this->postJson('/api/mobile/v1/auth/login', devicePayload([
        'email' => 'ariff@example.com',
        'password' => 'WrongPassword1',
    ]))
        ->assertStatus(401)
        ->assertJsonPath('code', 'INVALID_CREDENTIALS');
});

test('login requires device identification', function () {
    $this->postJson('/api/mobile/v1/auth/login', [
        'email' => 'ariff@example.com',
        'password' => 'Password123',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors(['device_name', 'device_id', 'platform']);
});

test('signing in again from the same device replaces the previous token', function () {
    $first = $this->postJson('/api/mobile/v1/auth/login', devicePayload([
        'email' => 'ariff@example.com',
        'password' => 'Password123',
    ]))->json('token');

    $second = $this->postJson('/api/mobile/v1/auth/login', devicePayload([
        'email' => 'ariff@example.com',
        'password' => 'Password123',
    ]))->json('token');

    expect($first)->not->toBe($second);
    expect($this->user->tokens()->count())->toBe(1);

    $this->withToken($first)->getJson('/api/mobile/v1/auth/me')->assertStatus(401);
    $this->withToken($second)->getJson('/api/mobile/v1/auth/me')->assertOk();
});

test('me requires a token', function () {
    $this->getJson('/api/mobile/v1/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('code', 'UNAUTHENTICATED');
});

test('logout revokes the token and the device push registration', function () {
    $token = $this->postJson('/api/mobile/v1/auth/login', devicePayload([
        'email' => 'ariff@example.com',
        'password' => 'Password123',
        'push_token' => 'fcm-token-xyz',
    ]))->json('token');

    $this->withToken($token)->postJson('/api/mobile/v1/auth/logout')->assertNoContent();

    expect($this->user->tokens()->count())->toBe(0);

    $device = UserDevice::query()->where('device_id', 'device-abc-123')->first();
    expect($device->push_token)->toBeNull()
        ->and($device->revoked_at)->not->toBeNull();
});

test('refresh swaps the token', function () {
    $token = $this->postJson('/api/mobile/v1/auth/login', devicePayload([
        'email' => 'ariff@example.com',
        'password' => 'Password123',
    ]))->json('token');

    $refreshed = $this->withToken($token)->postJson('/api/mobile/v1/auth/refresh')
        ->assertOk()
        ->json('token');

    expect($refreshed)->not->toBe($token);

    // The guard memoises its resolved user for the lifetime of the test
    // application, so it has to be cleared before a second identity is used.
    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/mobile/v1/auth/me')->assertStatus(401);

    $this->app['auth']->forgetGuards();
    $this->withToken($refreshed)->getJson('/api/mobile/v1/auth/me')->assertOk();
});

test('switching organization is refused for one the user does not belong to', function () {
    $other = Organization::factory()->create();

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/mobile/v1/auth/organization', ['organization_id' => $other->id])
        ->assertStatus(403)
        ->assertJsonPath('code', 'ORGANIZATION_FORBIDDEN');
});

test('switching organization persists the choice', function () {
    $second = Organization::factory()->create();
    $second->members()->attach($this->user, ['role' => UserRole::Member->value]);

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/mobile/v1/auth/organization', ['organization_id' => $second->id])
        ->assertOk();

    expect($this->user->fresh()->current_organization_id)->toBe($second->id);
});

test('forgot password never reveals whether the email exists', function () {
    $known = $this->postJson('/api/mobile/v1/auth/password/forgot', ['email' => 'ariff@example.com']);
    $unknown = $this->postJson('/api/mobile/v1/auth/password/forgot', ['email' => 'nobody@example.com']);

    $known->assertOk();
    $unknown->assertOk();
    expect($known->json('message'))->toBe($unknown->json('message'));
});
