<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the invite landing page offers to open the app and to install it', function () {
    config(['deeplink.scheme' => 'antaranote']);

    $token = str_repeat('a', 64);

    $this->get("/live/join/{$token}")
        ->assertOk()
        ->assertSee("antaranote://live/join/{$token}", false)
        ->assertSee('Open in antaraNote');
});

test('the apple association names the app and the join paths', function () {
    config(['deeplink.ios_app_id' => 'YGDAPZRD8V.cloud.antara.note']);

    $this->getJson('/.well-known/apple-app-site-association')
        ->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJsonPath('applinks.details.0.appID', 'YGDAPZRD8V.cloud.antara.note')
        ->assertJsonPath('applinks.details.0.paths', ['/live/join/*']);
});

test('the android association carries the package and its fingerprints', function () {
    config([
        'deeplink.android_package' => 'cloud.antara.note',
        'deeplink.android_sha256' => ['AA:BB:CC'],
    ]);

    $this->getJson('/.well-known/assetlinks.json')
        ->assertOk()
        ->assertJsonPath('0.target.namespace', 'android_app')
        ->assertJsonPath('0.target.package_name', 'cloud.antara.note')
        ->assertJsonPath('0.target.sha256_cert_fingerprints', ['AA:BB:CC']);
});
