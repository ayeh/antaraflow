<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Services\SocialAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class SocialAuthController extends Controller
{
    private const array SUPPORTED_PROVIDERS = ['google', 'microsoft', 'github'];

    public function __construct(
        private SocialAuthService $socialAuthService,
    ) {}

    public function redirect(string $provider): SymfonyRedirectResponse
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            abort(404);
        }

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            abort(404);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception) {
            return redirect()->route('login')
                ->withErrors(['social' => 'Unable to authenticate with '.ucfirst($provider).'. Please try again.']);
        }

        try {
            $user = $this->socialAuthService->findOrCreateUser($provider, $socialUser);
        } catch (\RuntimeException $e) {
            return redirect()->route('login')
                ->withErrors(['social' => $e->getMessage()]);
        }

        Auth::login($user);

        $user->update(['last_login_at' => now()]);

        return redirect()->intended(route('dashboard'));
    }

    public function unlink(string $provider): RedirectResponse
    {
        if (! in_array($provider, self::SUPPORTED_PROVIDERS, true)) {
            abort(404);
        }

        try {
            $this->socialAuthService->unlinkAccount(auth()->user(), $provider);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['social' => $e->getMessage()]);
        }

        return back()->with('success', ucfirst($provider).' account unlinked successfully.');
    }
}
