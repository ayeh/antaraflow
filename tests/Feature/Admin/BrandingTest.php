<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\PlatformSetting;
use App\Domain\Admin\Services\BrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

test('branding service returns defaults when no settings exist', function () {
    $service = app(BrandingService::class);

    $all = $service->all();

    expect($all)
        ->toBeArray()
        ->toHaveKey('app_name', 'antaraFLOW')
        ->toHaveKey('primary_color', '#7c3aed')
        ->toHaveKey('secondary_color', '#3b82f6')
        ->toHaveKey('footer_text', '')
        ->toHaveKey('support_email', '')
        ->toHaveKey('custom_css', '');
});

test('branding service returns stored values', function () {
    PlatformSetting::setValue('app_name', 'MyBrand');
    PlatformSetting::setValue('primary_color', '#ff0000');

    $service = app(BrandingService::class);

    expect($service->get('app_name'))->toBe('MyBrand');
    expect($service->get('primary_color'))->toBe('#ff0000');
    expect($service->appName())->toBe('MyBrand');
});

test('branding service cache works', function () {
    $service = app(BrandingService::class);

    // First call should cache the value
    $service->get('app_name');
    expect(Cache::has('branding.app_name'))->toBeTrue();

    // Clear cache
    $service->clearCache();
    expect(Cache::has('branding.app_name'))->toBeFalse();

    // Re-fetch should re-populate cache
    $service->get('app_name');
    expect(Cache::has('branding.app_name'))->toBeTrue();
});

test('admin can view branding page', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.branding.index'))
        ->assertStatus(200)
        ->assertSee('Platform Branding');
});

test('admin can update branding settings', function () {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), [
            'app_name' => 'NewBrand',
            'primary_color' => '#ff0000',
            'secondary_color' => '#00ff00',
            'footer_text' => 'Footer text here',
            'support_email' => 'help@example.com',
            'custom_css' => '',
            'custom_domain' => '',
            'logo_url' => '',
            'favicon_url' => '',
            'login_background_url' => '',
            'email_header_html' => '',
            'email_footer_html' => '',
        ])
        ->assertRedirect(route('admin.branding.index'))
        ->assertSessionHas('success', 'Branding settings updated successfully.');
});

test('updated branding persists in database', function () {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), [
            'app_name' => 'PersistBrand',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
            'footer_text' => 'My footer',
            'support_email' => 'support@test.com',
            'custom_css' => 'body { color: red; }',
            'custom_domain' => 'app.test.com',
            'logo_url' => 'https://example.com/logo.png',
            'favicon_url' => 'https://example.com/favicon.ico',
            'login_background_url' => 'https://example.com/bg.jpg',
            'email_header_html' => '<div>Header</div>',
            'email_footer_html' => '<div>Footer</div>',
        ]);

    expect(PlatformSetting::getValue('app_name'))->toBe('PersistBrand');
    expect(PlatformSetting::getValue('primary_color'))->toBe('#123456');
    expect(PlatformSetting::getValue('support_email'))->toBe('support@test.com');
    expect(PlatformSetting::getValue('custom_css'))->toBe('body { color: red; }');
    expect(PlatformSetting::getValue('custom_domain'))->toBe('app.test.com');
    expect(PlatformSetting::getValue('logo_url'))->toBe('https://example.com/logo.png');
});

test('unauthenticated user cannot access branding', function () {
    $this->get(route('admin.branding.index'))
        ->assertRedirect(route('admin.login'));
});

test('branding service includes new default keys', function () {
    $service = app(BrandingService::class);
    $all = $service->all();

    expect($all)
        ->toHaveKey('accent_color', '#10b981')
        ->toHaveKey('danger_color', '#ef4444')
        ->toHaveKey('success_color', '#22c55e')
        ->toHaveKey('heading_font', 'Inter')
        ->toHaveKey('body_font', 'Inter')
        ->toHaveKey('custom_themes', '[]')
        ->toHaveKey('logo_path', '')
        ->toHaveKey('favicon_path', '')
        ->toHaveKey('login_background_path', '');
});
