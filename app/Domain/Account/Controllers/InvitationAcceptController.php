<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\OrganizationInvitation;
use App\Domain\Account\Services\OrganizationService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InvitationAcceptController extends Controller
{
    public function __construct(
        private OrganizationService $organizationService,
    ) {}

    public function show(string $token): View
    {
        $invitation = OrganizationInvitation::query()->where('token', $token)->first();

        $existingUser = $invitation
            ? User::query()->where('email', $invitation->email)->exists()
            : false;

        return view('invitations.accept', [
            'invitation' => $invitation,
            'valid' => $invitation?->isValid() ?? false,
            'existingUser' => $existingUser,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = OrganizationInvitation::query()->where('token', $token)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return redirect()->route('invitations.accept.show', ['token' => $token])
                ->withErrors(['invitation' => __('This invitation is no longer valid.')]);
        }

        if (Auth::check()) {
            if (Auth::user()->email !== $invitation->email) {
                return redirect()->route('invitations.accept.show', ['token' => $token])
                    ->withErrors(['invitation' => __('This invitation was sent to a different email address. Please sign out and try again.')]);
            }

            $this->organizationService->acceptInvitation($invitation, Auth::user());

            return redirect()->route('dashboard')->with('success', __('You have joined :name.', ['name' => $invitation->organization->name]));
        }

        if (User::query()->where('email', $invitation->email)->exists()) {
            return redirect()->route('login')
                ->with('info', __('Please sign in to accept your invitation to :name.', ['name' => $invitation->organization->name]));
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $invitation->email,
            'password' => $validated['password'],
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        Auth::login($user);

        $this->organizationService->acceptInvitation($invitation, $user);

        return redirect()->route('dashboard')->with('success', __('Welcome to :name!', ['name' => $invitation->organization->name]));
    }
}
