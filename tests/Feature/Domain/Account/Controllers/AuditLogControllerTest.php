<?php

declare(strict_types=1);

use App\Domain\Account\Models\AuditLog;
use App\Domain\Account\Models\Organization;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner can view audit log', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    AuditLog::factory()->count(3)->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'action' => 'created',
    ]);

    $response = $this->actingAs($user)->get(route('audit-log.index'));

    $response->assertOk();
    $response->assertSee('Audit Log');
    $response->assertSee('created');
});

test('admin can view audit log', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Admin->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->get(route('audit-log.index'));

    $response->assertOk();
});

test('owner can filter audit log by action', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    AuditLog::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'action' => 'created',
        'new_values' => ['marker' => 'should-be-visible'],
    ]);

    AuditLog::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'action' => 'updated',
        'new_values' => ['marker' => 'should-be-hidden'],
    ]);

    $response = $this->actingAs($user)->get(route('audit-log.index', ['action' => 'created']));

    $response->assertOk();
    $response->assertSee('should-be-visible');
    $response->assertDontSee('should-be-hidden');
});

test('manager cannot view audit log', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Manager->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->get(route('audit-log.index'));

    $response->assertForbidden();
});

test('member cannot view audit log', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Member->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->get(route('audit-log.index'));

    $response->assertForbidden();
});

test('viewer cannot view audit log', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Viewer->value]);
    $user->update(['current_organization_id' => $org->id]);

    $response = $this->actingAs($user)->get(route('audit-log.index'));

    $response->assertForbidden();
});

test('unauthenticated user is redirected to login', function () {
    $response = $this->get(route('audit-log.index'));

    $response->assertRedirect(route('login'));
});

test('audit log only shows entries from current organization', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $org->members()->attach($user, ['role' => UserRole::Owner->value]);
    $user->update(['current_organization_id' => $org->id]);

    AuditLog::factory()->create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'action' => 'own_org_action',
    ]);

    AuditLog::factory()->create([
        'organization_id' => $otherOrg->id,
        'action' => 'other_org_action',
    ]);

    $response = $this->actingAs($user)->get(route('audit-log.index'));

    $response->assertOk();
    $response->assertSee('own_org_action');
    $response->assertDontSee('other_org_action');
    $response->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
});
