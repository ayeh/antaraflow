<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers\Auth;

use App\Domain\Account\Requests\RegisterRequest;
use App\Domain\Account\Services\OrganizationService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(
        private OrganizationService $organizationService,
    ) {}

    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        $this->organizationService->createOrganization(
            $user,
            $user->name."'s Workspace",
        );

        $user->sendEmailVerificationNotification();

        Auth::login($user);

        return redirect()->route('onboarding.step', ['step' => 1]);
    }
}
