<?php

declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Domain\Account\Mail\OrganizationInvitationMail;
use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationInvitation;
use App\Domain\Account\Models\OrganizationSubscription;
use App\Domain\Account\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OrganizationService
{
    public function __construct(
        private AuditService $auditService,
    ) {}

    public function createOrganization(User $user, string $name, ?string $slug = null, ?string $description = null): Organization
    {
        $organization = Organization::query()->create([
            'name' => $name,
            'slug' => $slug ?? Str::slug($name).'-'.Str::random(5),
            'description' => $description,
        ]);

        $organization->members()->attach($user, ['role' => UserRole::Owner->value]);

        $user->update(['current_organization_id' => $organization->id]);

        $freePlan = SubscriptionPlan::query()->where('slug', 'free')->first();

        if ($freePlan) {
            OrganizationSubscription::withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'subscription_plan_id' => $freePlan->id,
                'status' => 'active',
                'starts_at' => now(),
            ]);
        }

        return $organization;
    }

    public function inviteMember(Organization $organization, User $user, UserRole $role = UserRole::Member): void
    {
        $organization->members()->attach($user, ['role' => $role->value]);

        $this->auditService->log('member_invited', $organization, null, [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $role->value,
        ]);
    }

    public function inviteByEmail(Organization $organization, string $email, UserRole $role, User $inviter): OrganizationInvitation
    {
        $invitation = $organization->invitations()->create([
            'email' => $email,
            'role' => $role,
            'token' => OrganizationInvitation::generateToken(),
            'invited_by_user_id' => $inviter->id,
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($email)->send(new OrganizationInvitationMail($invitation));

        $this->auditService->log('member_invited', $invitation, null, [
            'email' => $email,
            'role' => $role->value,
        ]);

        return $invitation;
    }

    public function acceptInvitation(OrganizationInvitation $invitation, User $user): void
    {
        $organization = $invitation->organization;

        if (! $organization->members()->where('user_id', $user->id)->exists()) {
            $organization->members()->attach($user, ['role' => $invitation->role->value]);
        }

        $invitation->update(['accepted_at' => now()]);

        if (! $user->current_organization_id) {
            $user->update(['current_organization_id' => $organization->id]);
        }

        $this->auditService->log('invitation_accepted', $invitation, null, [
            'user_id' => $user->id,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
        ]);
    }

    public function resendInvitation(OrganizationInvitation $invitation): void
    {
        $invitation->update([
            'token' => OrganizationInvitation::generateToken(),
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($invitation->email)->send(new OrganizationInvitationMail($invitation));

        $this->auditService->log('invitation_resent', $invitation, null, [
            'email' => $invitation->email,
        ]);
    }

    public function revokeInvitation(OrganizationInvitation $invitation): void
    {
        $this->auditService->log('invitation_revoked', $invitation, [
            'email' => $invitation->email,
        ]);

        $invitation->delete();
    }

    public function removeMember(Organization $organization, User $user): void
    {
        $oldRole = $organization->members()->where('user_id', $user->id)->first()?->pivot->role;

        $organization->members()->detach($user);

        if ($user->current_organization_id === $organization->id) {
            $nextOrg = $user->organizations()->first();
            $user->update(['current_organization_id' => $nextOrg?->id]);
        }

        $this->auditService->log('member_removed', $organization, [
            'user_id' => $user->id,
            'role' => $oldRole,
        ]);
    }

    public function changeRole(Organization $organization, User $user, UserRole $newRole): void
    {
        $oldRole = $organization->members()->where('user_id', $user->id)->first()?->pivot->role;

        $organization->members()->updateExistingPivot($user->id, ['role' => $newRole->value]);

        $this->auditService->log('role_changed', $organization, [
            'user_id' => $user->id,
            'old_role' => $oldRole,
        ], [
            'user_id' => $user->id,
            'new_role' => $newRole->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function updateSettings(Organization $organization, array $settings): Organization
    {
        $oldSettings = $organization->settings;

        $merged = array_replace_recursive($organization->settings ?? [], $settings);
        $organization->update(['settings' => $merged]);

        $this->auditService->log('settings_updated', $organization, [
            'settings' => $oldSettings,
        ], [
            'settings' => $merged,
        ]);

        return $organization->fresh();
    }
}
