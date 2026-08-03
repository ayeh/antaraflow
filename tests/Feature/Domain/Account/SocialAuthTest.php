<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\SocialAccount;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create([
        'current_organization_id' => $this->org->id,
    ]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    config()->set('services.google.client_id', 'test-google-id');
    config()->set('services.microsoft.client_id', 'test-microsoft-id');
    config()->set('services.github.client_id', 'test-github-id');
    config()->set('services.mydigitalid.client_id', 'test-mydigitalid-id');
});

it('redirects to google oauth provider', function () {
    Socialite::fake('google');

    $response = $this->get(route('social.redirect', 'google'));

    $response->assertRedirect();
});

it('returns 404 for invalid provider', function () {
    $response = $this->get(route('social.redirect', 'invalid-provider'));

    $response->assertNotFound();
});

it('creates a new user from google callback', function () {
    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-12345',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'avatar' => 'https://example.com/avatar.jpg',
    ]));

    $response = $this->get(route('social.callback', 'google'));

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $this->assertDatabaseHas('social_accounts', [
        'provider' => 'google',
        'provider_id' => 'google-12345',
        'provider_email' => 'jane@example.com',
    ]);

    $this->assertAuthenticated();
});

it('rejects social callback when email matches an existing account without that social link', function () {
    User::factory()->create([
        'email' => 'existing@example.com',
        'current_organization_id' => $this->org->id,
    ]);

    Socialite::fake('github', (new SocialiteUser)->map([
        'id' => 'github-99999',
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'avatar' => 'https://example.com/avatar.jpg',
    ]));

    $response = $this->get(route('social.callback', 'github'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors();

    $this->assertDatabaseMissing('social_accounts', [
        'provider' => 'github',
        'provider_id' => 'github-99999',
    ]);
});

it('logs in existing linked user on callback', function () {
    $socialAccount = SocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'provider' => 'microsoft',
        'provider_id' => 'ms-55555',
        'provider_email' => $this->user->email,
    ]);

    Socialite::fake('microsoft', (new SocialiteUser)->map([
        'id' => 'ms-55555',
        'name' => $this->user->name,
        'email' => $this->user->email,
        'avatar' => 'https://example.com/avatar.jpg',
    ]));

    $response = $this->get(route('social.callback', 'microsoft'));

    $response->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->user);

    expect(SocialAccount::query()->where('provider', 'microsoft')->count())->toBe(1);
});

it('unlinks a social account', function () {
    SocialAccount::factory()->create([
        'user_id' => $this->user->id,
        'provider' => 'google',
        'provider_id' => 'google-111',
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('social.unlink', 'google'));

    $response->assertRedirect();

    $this->assertDatabaseMissing('social_accounts', [
        'user_id' => $this->user->id,
        'provider' => 'google',
    ]);
});

it('cannot unlink when it is the only auth method', function () {
    $userWithoutPassword = User::factory()->create([
        'password' => null,
        'current_organization_id' => $this->org->id,
    ]);
    $this->org->members()->attach($userWithoutPassword, ['role' => UserRole::Member->value]);

    SocialAccount::factory()->create([
        'user_id' => $userWithoutPassword->id,
        'provider' => 'google',
        'provider_id' => 'google-only',
    ]);

    $response = $this->actingAs($userWithoutPassword)
        ->delete(route('social.unlink', 'google'));

    $response->assertRedirect();
    $response->assertSessionHasErrors('social');

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $userWithoutPassword->id,
        'provider' => 'google',
    ]);
});

it('shows social login buttons on login page', function () {
    $response = $this->get(route('login'));

    $response->assertSuccessful();
    $response->assertSee('or continue with');
    $response->assertSee('Google');
    $response->assertSee('Microsoft');
    $response->assertSee('GitHub');
    $response->assertSee(route('social.redirect', 'google'));
    $response->assertSee(route('social.redirect', 'microsoft'));
    $response->assertSee(route('social.redirect', 'github'));
});

it('shows the mydigital id login button on login page', function () {
    $response = $this->get(route('login'));

    $response->assertSuccessful();
    $response->assertSee('MyDigital ID');
    $response->assertSee(route('social.redirect', 'mydigitalid'));
});

it('hides a social login button when the provider is not configured', function () {
    config()->set('services.mydigitalid.client_id', null);

    $response = $this->get(route('login'));

    $response->assertSuccessful();
    $response->assertDontSee('MyDigital ID');
    $response->assertDontSee(route('social.redirect', 'mydigitalid'));
});

it('builds a valid mydigital id authorization redirect from config', function () {
    config()->set('services.mydigitalid', [
        'client_id' => 'test-client',
        'client_secret' => 'test-secret',
        'redirect' => '/auth/mydigitalid/callback',
        'base_url' => 'https://sandbox.digital-id.my',
        'authorize_uri' => null,
        'token_uri' => null,
        'userinfo_uri' => null,
        'scopes' => ['openid', 'profile', 'email'],
        'pkce' => false,
    ]);

    $response = $this->get(route('social.redirect', 'mydigitalid'));

    $response->assertRedirect();

    $location = $response->headers->get('Location');
    expect($location)->toStartWith('https://sandbox.digital-id.my/auth?');
    expect($location)->toContain('client_id=test-client');
    expect($location)->toContain('scope=openid+profile+email');
    expect($location)->toContain('response_type=code');
});

it('creates a new user from mydigital id callback', function () {
    Socialite::fake('mydigitalid', (new SocialiteUser)->map([
        'id' => 'myid-sub-abc123',
        'name' => 'Ahmad Bin Ali',
        'email' => 'ahmad@example.my',
        'avatar' => null,
    ]));

    $response = $this->get(route('social.callback', 'mydigitalid'));

    $response->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('users', [
        'name' => 'Ahmad Bin Ali',
        'email' => 'ahmad@example.my',
    ]);

    $this->assertDatabaseHas('social_accounts', [
        'provider' => 'mydigitalid',
        'provider_id' => 'myid-sub-abc123',
        'provider_email' => 'ahmad@example.my',
    ]);

    $this->assertAuthenticated();
});
