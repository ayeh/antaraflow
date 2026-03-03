<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\SmtpConfiguration;
use App\Domain\Admin\Services\SmtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

test('admin can view smtp configuration page', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.smtp.index'))
        ->assertStatus(200)
        ->assertSee('SMTP Configuration');
});

test('admin can save global smtp configuration', function () {
    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.smtp.update-global'), [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'user@example.com',
            'password' => 'secret123',
            'encryption' => 'tls',
            'from_address' => 'noreply@example.com',
            'from_name' => 'AntaraFlow',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.smtp.index'));

    $this->assertDatabaseHas('smtp_configurations', [
        'host' => 'smtp.example.com',
        'port' => 587,
        'organization_id' => null,
    ]);
});

test('admin can save org smtp configuration', function () {
    $org = Organization::factory()->create();

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.smtp.update-org', $org), [
            'host' => 'smtp.org.com',
            'port' => 465,
            'username' => 'org@example.com',
            'password' => 'orgpass',
            'encryption' => 'ssl',
            'from_address' => 'noreply@org.com',
            'from_name' => 'Org Email',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.smtp.org-index'));

    $this->assertDatabaseHas('smtp_configurations', [
        'host' => 'smtp.org.com',
        'organization_id' => $org->id,
    ]);
});

test('smtp service resolves org config with global fallback', function () {
    $globalConfig = SmtpConfiguration::query()->create([
        'organization_id' => null,
        'host' => 'global.smtp.com',
        'port' => 587,
        'username' => 'global',
        'password' => 'pass',
        'encryption' => 'tls',
        'from_address' => 'global@test.com',
        'from_name' => 'Global',
        'is_active' => true,
    ]);

    $service = app(SmtpService::class);

    // Org without config falls back to global
    $config = $service->getConfigForOrganization(9999);
    expect($config->id)->toBe($globalConfig->id);
});

test('smtp passwords are encrypted in database', function () {
    $config = SmtpConfiguration::query()->create([
        'organization_id' => null,
        'host' => 'test.smtp.com',
        'port' => 587,
        'username' => 'testuser',
        'password' => 'plaintext123',
        'encryption' => 'tls',
        'from_address' => 'test@test.com',
        'from_name' => 'Test',
        'is_active' => true,
    ]);

    // Raw DB value should NOT be plaintext
    $rawPassword = DB::table('smtp_configurations')->where('id', $config->id)->value('password');
    expect($rawPassword)->not->toBe('plaintext123');

    // Decrypted value should match
    expect($config->decrypted_password)->toBe('plaintext123');
});

test('unauthenticated cannot access smtp', function () {
    $this->get(route('admin.smtp.index'))
        ->assertRedirect(route('admin.login'));
});
