<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\PlatformSetting;
use App\Domain\Admin\Services\BrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

function basePayload(): array
{
    return [
        'app_name' => 'TestBrand',
        'primary_color' => '#7c3aed',
        'secondary_color' => '#3b82f6',
        'accent_color' => '#10b981',
        'danger_color' => '#ef4444',
        'success_color' => '#22c55e',
        'heading_font' => 'Inter',
        'body_font' => 'Inter',
        'footer_text' => '',
        'support_email' => '',
        'custom_css' => '',
        'custom_domain' => '',
        'logo_url' => '',
        'favicon_url' => '',
        'login_background_url' => '',
        'email_header_html' => '',
        'email_footer_html' => '',
    ];
}

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
        ->put(route('admin.branding.update'), basePayload())
        ->assertRedirect(route('admin.branding.index'))
        ->assertSessionHas('success', 'Branding settings updated successfully.');
});

test('updated branding persists in database', function () {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), array_merge(basePayload(), [
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
        ]));

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

test('admin can upload logo file', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('logo.png', 200, 200);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), array_merge(basePayload(), [
            'logo' => $file,
        ]))
        ->assertRedirect(route('admin.branding.index'));

    Storage::disk('public')->assertExists('branding/'.$file->hashName());
    expect(PlatformSetting::getValue('logo_path'))->toContain('branding/');
});

test('admin can upload favicon file', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('favicon.ico', 32, 32);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), array_merge(basePayload(), [
            'favicon' => $file,
        ]))
        ->assertRedirect(route('admin.branding.index'));

    Storage::disk('public')->assertExists('branding/'.$file->hashName());
    expect(PlatformSetting::getValue('favicon_path'))->toContain('branding/');
});

test('logo upload rejects non-image files', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('malware.php', 100, 'application/php');

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), array_merge(basePayload(), [
            'logo' => $file,
        ]))
        ->assertSessionHasErrors('logo');
});

test('admin can save a custom theme preset', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(route('admin.branding.presets.store'), [
            'name' => 'My Theme',
            'primary_color' => '#ff0000',
            'secondary_color' => '#00ff00',
            'accent_color' => '#0000ff',
            'danger_color' => '#ff4444',
            'success_color' => '#44ff44',
            'heading_font' => 'Poppins',
            'body_font' => 'Inter',
        ])
        ->assertJson(['success' => true]);

    $themes = json_decode(PlatformSetting::getValue('custom_themes', '[]'), true);
    expect($themes)->toHaveCount(1);
    expect($themes[0]['name'])->toBe('My Theme');
});

test('admin can delete a custom theme preset', function () {
    PlatformSetting::setValue('custom_themes', json_encode([
        ['name' => 'My Theme', 'primary_color' => '#ff0000'],
    ]));

    $this->actingAs($this->admin, 'admin')
        ->deleteJson(route('admin.branding.presets.destroy', 'My Theme'))
        ->assertJson(['success' => true]);

    $themes = json_decode(PlatformSetting::getValue('custom_themes', '[]'), true);
    expect($themes)->toHaveCount(0);
});

test('admin can upload login background file', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('background.jpg', 1920, 1080);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), array_merge(basePayload(), [
            'login_background' => $file,
        ]))
        ->assertRedirect(route('admin.branding.index'));

    Storage::disk('public')->assertExists('branding/'.$file->hashName());
    expect(PlatformSetting::getValue('login_background_path'))->toContain('branding/');
});

test('logo upload rejects files over 2mb', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('logo.png')->size(2049);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), array_merge(basePayload(), [
            'logo' => $file,
        ]))
        ->assertSessionHasErrors('logo');
});

test('login background upload rejects files over 5mb', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('bg.jpg')->size(5121);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), array_merge(basePayload(), [
            'login_background' => $file,
        ]))
        ->assertSessionHasErrors('login_background');
});

test('saving a preset with duplicate name replaces the existing one', function () {
    $this->actingAs($this->admin, 'admin')
        ->postJson(route('admin.branding.presets.store'), [
            'name' => 'My Theme',
            'primary_color' => '#ff0000',
            'secondary_color' => '#00ff00',
        ])
        ->assertJson(['success' => true]);

    $this->actingAs($this->admin, 'admin')
        ->postJson(route('admin.branding.presets.store'), [
            'name' => 'My Theme',
            'primary_color' => '#123456',
            'secondary_color' => '#654321',
        ])
        ->assertJson(['success' => true]);

    $themes = json_decode(PlatformSetting::getValue('custom_themes', '[]'), true);
    expect($themes)->toHaveCount(1);
    expect($themes[0]['primary_color'])->toBe('#123456');
});

test('deleting a non-existent preset name succeeds idempotently', function () {
    $this->actingAs($this->admin, 'admin')
        ->deleteJson(route('admin.branding.presets.destroy', 'does-not-exist'))
        ->assertJson(['success' => true]);
});

test('unauthenticated user cannot access preset endpoints', function () {
    $this->postJson(route('admin.branding.presets.store'), [])
        ->assertRedirect(route('admin.login'));

    $this->deleteJson(route('admin.branding.presets.destroy', 'test'))
        ->assertRedirect(route('admin.login'));
});

test('uploading a new logo deletes the old file', function () {
    Storage::fake('public');

    $oldFile = UploadedFile::fake()->image('old-logo.png');
    $oldPath = $oldFile->store('branding', 'public');
    PlatformSetting::setValue('logo_path', $oldPath);

    $newFile = UploadedFile::fake()->image('new-logo.png');

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.branding.update'), array_merge(basePayload(), [
            'logo' => $newFile,
        ]))
        ->assertRedirect(route('admin.branding.index'));

    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists('branding/'.$newFile->hashName());
});
