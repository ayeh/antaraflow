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

    /** @var array<string, int> */
    private const array ROLE_HIERARCHY = [
        'viewer' => 0,
        'member' => 1,
        'manager' => 2,
        'admin' => 3,
        'owner' => 4,
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

        return $this->roleLevel($role) >= $this->roleLevel($minimumRole);
    }

    public function roleLevel(UserRole $role): int
    {
        return self::ROLE_HIERARCHY[$role->value] ?? -1;
    }

    /**
     * Whether the actor is allowed to grant the given role. Owners may grant any
     * role; everyone else may only grant roles strictly below their own.
     */
    public function canAssignRole(User $actor, Organization $organization, UserRole $role): bool
    {
        $actorRole = $this->getUserRole($actor, $organization);

        if (! $actorRole) {
            return false;
        }

        if ($actorRole === UserRole::Owner) {
            return true;
        }

        return $this->roleLevel($role) < $this->roleLevel($actorRole);
    }

    /**
     * Whether the actor is allowed to modify or remove the target member. Owners may
     * manage anyone; everyone else may only manage members whose current role is
     * strictly below their own (so admins cannot touch owners, other admins, or themselves).
     */
    public function canManageMember(User $actor, Organization $organization, User $target): bool
    {
        $actorRole = $this->getUserRole($actor, $organization);
        $targetRole = $this->getUserRole($target, $organization);

        if (! $actorRole || ! $targetRole) {
            return false;
        }

        if ($actorRole === UserRole::Owner) {
            return true;
        }

        return $this->roleLevel($targetRole) < $this->roleLevel($actorRole);
    }

    /**
     * The roles the actor is permitted to assign within the organization.
     *
     * @return array<int, UserRole>
     */
    public function assignableRoles(User $actor, Organization $organization): array
    {
        return array_values(array_filter(
            UserRole::cases(),
            fn (UserRole $role): bool => $this->canAssignRole($actor, $organization, $role),
        ));
    }
}
