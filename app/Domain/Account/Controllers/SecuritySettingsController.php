<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Requests\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SecuritySettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.security');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ]);

        Auth::logoutOtherDevices($request->validated('password'));

        return redirect()->route('settings.security')->with('success', 'Password updated.');
    }
}
