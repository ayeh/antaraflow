<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Requests\InviteMemberRequest;
use App\Domain\Account\Services\OrganizationService;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class MemberController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private OrganizationService $organizationService,
    ) {}

    public function index(Organization $organization): View
    {
        $this->authorize('view', $organization);

        $members = $organization->members;

        return view('organizations.members.index', compact('organization', 'members'));
    }

    public function store(InviteMemberRequest $request, Organization $organization): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);

        $user = User::query()->where('email', $request->validated('email'))->firstOrFail();
        $role = UserRole::from($request->validated('role'));

        $this->organizationService->inviteMember($organization, $user, $role);

        return redirect()->route('organizations.members.index', $organization)
            ->with('success', 'Member invited successfully.');
    }

    public function update(Request $request, User $member): RedirectResponse
    {
        $organization = $member->organizations()
            ->where('organization_id', $request->user()->current_organization_id)
            ->firstOrFail();

        $this->authorize('manageMembers', $organization);

        $request->validate([
            'role' => ['required', 'in:'.implode(',', array_column(UserRole::cases(), 'value'))],
        ]);

        $this->organizationService->changeRole($organization, $member, UserRole::from($request->input('role')));

        return redirect()->back()->with('success', 'Member role updated successfully.');
    }

    public function destroy(Request $request, User $member): RedirectResponse
    {
        $organization = $member->organizations()
            ->where('organization_id', $request->user()->current_organization_id)
            ->firstOrFail();

        $this->authorize('manageMembers', $organization);

        $this->organizationService->removeMember($organization, $member);

        return redirect()->back()->with('success', 'Member removed successfully.');
    }
}
