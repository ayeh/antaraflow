<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminSwitchController extends Controller
{
    public function toAdmin(Request $request): RedirectResponse
    {
        abort_if($request->session()->has('admin_impersonating'), 403);

        $adminAccount = $request->user()->adminAccount;

        abort_if(! $adminAccount, 403);

        auth('admin')->login($adminAccount);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')
            ->with('success', __('Switched to Admin Panel.'));
    }

    public function toUser(Request $request): RedirectResponse
    {
        $user = auth('admin')->user()->user;

        abort_if(! $user, 403);

        auth('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', __('Switched back to your account.'));
    }
}
