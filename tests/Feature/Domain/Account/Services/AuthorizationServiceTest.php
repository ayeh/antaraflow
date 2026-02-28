<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Services\AuthorizationService;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new AuthorizationService;
    $this->organization = Organization::factory()->create();
});

test('owner has all permissions', function () {
    $user = User::factory()->create();
    $this->organization->members()->attach($user, ['role' => UserRole::Owner->value]);

    expect($this->service->hasPermission($user, $this->organization, 'manage_organization'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_billing'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_members'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_roles'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_settings'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'create_meeting'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'edit_meeting'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'delete_meeting'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'view_meeting'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_templates'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'view_audit_log'))->toBeTrue();
});

test('viewer can only view meetings', function () {
    $user = User::factory()->create();
    $this->organization->members()->attach($user, ['role' => UserRole::Viewer->value]);

    expect($this->service->hasPermission($user, $this->organization, 'view_meeting'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'create_meeting'))->toBeFalse()
        ->and($this->service->hasPermission($user, $this->organization, 'edit_meeting'))->toBeFalse()
        ->and($this->service->hasPermission($user, $this->organization, 'delete_meeting'))->toBeFalse()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_organization'))->toBeFalse()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_members'))->toBeFalse();
});

test('non-member has no permissions', function () {
    $user = User::factory()->create();

    expect($this->service->hasPermission($user, $this->organization, 'view_meeting'))->toBeFalse()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_organization'))->toBeFalse()
        ->and($this->service->getUserRole($user, $this->organization))->toBeNull();
});

test('isAtLeast checks role hierarchy', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $viewer = User::factory()->create();

    $this->organization->members()->attach($owner, ['role' => UserRole::Owner->value]);
    $this->organization->members()->attach($admin, ['role' => UserRole::Admin->value]);
    $this->organization->members()->attach($member, ['role' => UserRole::Member->value]);
    $this->organization->members()->attach($viewer, ['role' => UserRole::Viewer->value]);

    expect($this->service->isAtLeast($owner, $this->organization, UserRole::Owner))->toBeTrue()
        ->and($this->service->isAtLeast($owner, $this->organization, UserRole::Viewer))->toBeTrue()
        ->and($this->service->isAtLeast($admin, $this->organization, UserRole::Admin))->toBeTrue()
        ->and($this->service->isAtLeast($admin, $this->organization, UserRole::Owner))->toBeFalse()
        ->and($this->service->isAtLeast($member, $this->organization, UserRole::Member))->toBeTrue()
        ->and($this->service->isAtLeast($member, $this->organization, UserRole::Admin))->toBeFalse()
        ->and($this->service->isAtLeast($viewer, $this->organization, UserRole::Viewer))->toBeTrue()
        ->and($this->service->isAtLeast($viewer, $this->organization, UserRole::Member))->toBeFalse();
});

test('getUserRole returns correct role', function () {
    $user = User::factory()->create();
    $this->organization->members()->attach($user, ['role' => UserRole::Admin->value]);

    expect($this->service->getUserRole($user, $this->organization))->toBe(UserRole::Admin);
});

test('member has create and edit but not delete permissions', function () {
    $user = User::factory()->create();
    $this->organization->members()->attach($user, ['role' => UserRole::Member->value]);

    expect($this->service->hasPermission($user, $this->organization, 'create_meeting'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'edit_meeting'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'view_meeting'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'delete_meeting'))->toBeFalse()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_templates'))->toBeFalse();
});

test('manager can delete meetings and manage templates', function () {
    $user = User::factory()->create();
    $this->organization->members()->attach($user, ['role' => UserRole::Manager->value]);

    expect($this->service->hasPermission($user, $this->organization, 'create_meeting'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'delete_meeting'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_templates'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_members'))->toBeFalse()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_settings'))->toBeFalse();
});

test('admin can manage members but not organization', function () {
    $user = User::factory()->create();
    $this->organization->members()->attach($user, ['role' => UserRole::Admin->value]);

    expect($this->service->hasPermission($user, $this->organization, 'manage_members'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_settings'))->toBeTrue()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_organization'))->toBeFalse()
        ->and($this->service->hasPermission($user, $this->organization, 'manage_billing'))->toBeFalse();
});
