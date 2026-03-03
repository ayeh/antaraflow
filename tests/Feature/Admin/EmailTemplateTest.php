<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = Admin::factory()->create();
});

test('admin can view email templates index', function () {
    EmailTemplate::query()->create([
        'slug' => 'test-template',
        'name' => 'Test Template',
        'subject' => 'Test Subject',
        'body_html' => '<p>Test Body</p>',
        'variables' => ['user_name'],
        'is_active' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.email-templates.index'))
        ->assertStatus(200)
        ->assertSee('Test Template');
});

test('admin can edit an email template', function () {
    $template = EmailTemplate::query()->create([
        'slug' => 'edit-test',
        'name' => 'Edit Test',
        'subject' => 'Old Subject',
        'body_html' => '<p>Old Body</p>',
        'variables' => ['user_name'],
        'is_active' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->get(route('admin.email-templates.edit', $template))
        ->assertStatus(200)
        ->assertSee('Edit Test');
});

test('admin can update an email template', function () {
    $template = EmailTemplate::query()->create([
        'slug' => 'update-test',
        'name' => 'Update Test',
        'subject' => 'Old Subject',
        'body_html' => '<p>Old</p>',
        'variables' => ['user_name'],
        'is_active' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.email-templates.update', $template), [
            'name' => 'Updated Name',
            'subject' => 'New Subject {{user_name}}',
            'body_html' => '<h1>Hello {{user_name}}</h1>',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.email-templates.index'));

    expect($template->fresh()->subject)->toBe('New Subject {{user_name}}');
});

test('admin can preview an email template', function () {
    $template = EmailTemplate::query()->create([
        'slug' => 'preview-test',
        'name' => 'Preview Test',
        'subject' => 'Welcome {{user_name}}',
        'body_html' => '<p>Hello {{user_name}} from {{app_name}}</p>',
        'variables' => ['user_name', 'app_name'],
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->admin, 'admin')
        ->postJson(route('admin.email-templates.preview', $template));

    $response->assertStatus(200);
    $response->assertJsonStructure(['subject', 'body']);
    expect($response->json('subject'))->toContain('John Doe');
});

test('admin can deactivate a template', function () {
    $template = EmailTemplate::query()->create([
        'slug' => 'deactivate-test',
        'name' => 'Deactivate Test',
        'subject' => 'Test',
        'body_html' => '<p>Test</p>',
        'variables' => [],
        'is_active' => true,
    ]);

    $this->actingAs($this->admin, 'admin')
        ->put(route('admin.email-templates.update', $template), [
            'name' => 'Deactivate Test',
            'subject' => 'Test',
            'body_html' => '<p>Test</p>',
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($template->fresh()->is_active)->toBeFalse();
});

test('unauthenticated cannot access email templates', function () {
    $this->get(route('admin.email-templates.index'))
        ->assertRedirect(route('admin.login'));
});
