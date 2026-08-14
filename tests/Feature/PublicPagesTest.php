<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the public landing page', function () {
    $this->get(route('about'))
        ->assertOk()
        ->assertSee('Sign in');
});

it('renders the terms page', function () {
    $this->get(route('terms'))
        ->assertOk()
        ->assertSee('Terms of Service');
});

it('renders the privacy policy with the Google Limited Use disclosure', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('Google API Services User Data Policy')
        ->assertSee('Limited Use');
});

it('privacy and terms are reachable without authentication', function () {
    $this->get('/privacy')->assertOk();
    $this->get('/terms')->assertOk();
    $this->get('/about')->assertOk();
});

it('renders the account deletion page Google Play links to', function () {
    $this->get(route('account-deletion'))
        ->assertOk()
        ->assertSee('Deleting your account')
        ->assertSee('support@antara.cloud')
        ->assertSee('within 30 days');
});

it('the account deletion page is reachable without authentication', function () {
    $this->get('/account-deletion')->assertOk();
});
