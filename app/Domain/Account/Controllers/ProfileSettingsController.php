<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\UserSettings;
use App\Domain\Account\Requests\UpdateProfileSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ProfileSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $settings = UserSettings::firstOrNew(['user_id' => $request->user()->id]);

        return view('settings.profile', ['user' => $request->user(), 'settings' => $settings]);
    }

    public function update(UpdateProfileSettingsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->update(['name' => $request->validated('name')]);

        UserSettings::updateOrCreate(
            ['user_id' => $user->id],
            [
                'timezone' => $request->validated('timezone', 'UTC'),
                'locale' => $request->validated('locale', 'en'),
            ]
        );

        return redirect()->route('settings.profile')->with('success', 'Profile updated.');
    }
}
