<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Models\OrganizationInvitation;
use App\Domain\Account\Services\OrganizationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

class InvitationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private OrganizationService $organizationService,
    ) {}

    public function resend(Organization $organization, OrganizationInvitation $invitation): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);
        abort_unless($invitation->organization_id === $organization->id, Response::HTTP_NOT_FOUND);

        $this->organizationService->resendInvitation($invitation);

        return redirect()->back()->with('success', __('Invitation resent successfully.'));
    }

    public function destroy(Organization $organization, OrganizationInvitation $invitation): RedirectResponse
    {
        $this->authorize('manageMembers', $organization);
        abort_unless($invitation->organization_id === $organization->id, Response::HTTP_NOT_FOUND);

        $this->organizationService->revokeInvitation($invitation);

        return redirect()->back()->with('success', __('Invitation revoked successfully.'));
    }
}
