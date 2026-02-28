<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers\Auth;

use App\Domain\Account\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $request->user()->update(['last_login_at' => now()]);

        return redirect()->intended(route('organizations.index'));
    }
}
