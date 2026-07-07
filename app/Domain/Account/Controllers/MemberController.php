<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Requests\InviteMemberRequest;
use App\Domain\Account\Services\AuthorizationService;
use App\Domain\Account\Services\OrganizationService;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MemberController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private OrganizationService $organizationService,
        private AuthorizationService $authorizationService,
    ) {}

    public function index(Request $request, Organization $organization): View
    {
        $this->authorize('view', $organization);

        $user = $request->user();
        $members = $organization->members;
        $invitations = $organization->invitations()->pending()->latest()->get();
        $assignableRoles = $this->authorizationService->assignableRoles($user, $organization);
        $manageable = $members
            ->mapWithKeys(fn (User $member): array => [
                $member->id => $this->authorizationService->canManageMember($user, $organization, $member),
            ])
            ->all();

        return view('organizations.members.index', compact('organization', 'members', 'invitations', 'assignableRoles', 'manageable'));
    }

    public function store(InviteMemberRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        $this->organizationService->inviteByEmail(
            $organization,
            $request->validated('email'),
            UserRole::from($request->validated('role')),
            $request->user(),
        );

        return redirect()->route('organizations.members.index', $organization)
            ->with('success', __('Invitation sent successfully.'));
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $organization = $member->organizations()
            ->where('organization_id', $request->user()->current_organization_id)
            ->firstOrFail();

        $this->authorize('manageMembers', $organization);

        $validated = $request->validate([
            'role' => ['required', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
        ]);

        $newRole = UserRole::from($validated['role']);

        abort_unless(
            $this->authorizationService->canManageMember($request->user(), $organization, $member)
                && $this->authorizationService->canAssignRole($request->user(), $organization, $newRole),
            Response::HTTP_FORBIDDEN,
        );

        if ($newRole !== UserRole::Owner && $this->isLastOwner($organization, $member)) {
            return redirect()->back()->withErrors([
                'role' => __('You cannot change the role of the last owner.'),
            ]);
        }

        $this->organizationService->changeRole($organization, $member, $newRole);

        return redirect()->back()->with('success', __('Member role updated successfully.'));
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        $organization = $member->organizations()
            ->where('organization_id', $request->user()->current_organization_id)
            ->firstOrFail();

        $this->authorize('manageMembers', $organization);

        abort_unless(
            $this->authorizationService->canManageMember($request->user(), $organization, $member),
            Response::HTTP_FORBIDDEN,
        );

        if ($this->isLastOwner($organization, $member)) {
            return redirect()->back()->withErrors([
                'member' => __('You cannot remove the last owner of the organization.'),
            ]);
        }

        $this->organizationService->removeMember($organization, $member);

        return redirect()->back()->with('success', __('Member removed successfully.'));
    }

    private function isLastOwner(Organization $organization, User $member): bool
    {
        $memberRole = $organization->members()->where('user_id', $member->id)->first()?->pivot->role;

        if ($memberRole !== UserRole::Owner->value) {
            return false;
        }

        return $organization->members()->wherePivot('role', UserRole::Owner->value)->count() <= 1;
    }
}
