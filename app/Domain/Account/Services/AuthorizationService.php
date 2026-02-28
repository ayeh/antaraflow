<?php

declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Domain\Account\Models\Organization;
use App\Models\User;
use App\Support\Enums\UserRole;

class AuthorizationService
{
    /** @var array<string, array<string>> */
    private const array ROLE_PERMISSIONS = [
        'owner' => ['manage_organization', 'manage_billing', 'manage_members', 'manage_roles', 'manage_settings', 'create_meeting', 'edit_meeting', 'delete_meeting', 'view_meeting', 'manage_templates', 'view_audit_log'],
        'admin' => ['manage_members', 'manage_roles', 'manage_settings', 'create_meeting', 'edit_meeting', 'delete_meeting', 'view_meeting', 'manage_templates', 'view_audit_log'],
        'manager' => ['create_meeting', 'edit_meeting', 'delete_meeting', 'view_meeting', 'manage_templates'],
        'member' => ['create_meeting', 'edit_meeting', 'view_meeting'],
        'viewer' => ['view_meeting'],
    ];

    public function getUserRole(User $user, Organization $organization): ?UserRole
    {
        $membership = $user->organizations()->where('organization_id', $organization->id)->first();

        if (! $membership) {
            return null;
        }

        return UserRole::from($membership->pivot->role);
    }

    public function hasPermission(User $user, Organization $organization, string $permission): bool
    {
        $role = $this->getUserRole($user, $organization);

        if (! $role) {
            return false;
        }

        return in_array($permission, self::ROLE_PERMISSIONS[$role->value] ?? []);
    }

    public function isAtLeast(User $user, Organization $organization, UserRole $minimumRole): bool
    {
        $role = $this->getUserRole($user, $organization);

        if (! $role) {
            return false;
        }

        $hierarchy = [
            UserRole::Viewer->value => 0,
            UserRole::Member->value => 1,
            UserRole::Manager->value => 2,
            UserRole::Admin->value => 3,
            UserRole::Owner->value => 4,
        ];

        return ($hierarchy[$role->value] ?? -1) >= ($hierarchy[$minimumRole->value] ?? 999);
    }
}
