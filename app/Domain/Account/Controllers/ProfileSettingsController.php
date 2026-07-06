<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\UserSettings;
use App\Domain\Account\Requests\UpdatePasswordRequest;
use App\Domain\Account\Requests\UpdateProfileSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $locale = $request->validated('locale', 'en');

        UserSettings::updateOrCreate(
            ['user_id' => $user->id],
            [
                'timezone' => $request->validated('timezone', 'UTC'),
                'locale' => $locale,
            ]
        );

        $request->session()->put('locale', $locale);

        return redirect()->route('settings.profile')->with('success', __('settings.updated'));
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return redirect()->route('settings.profile')->with('success', __('settings.photo_updated'));
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ]);

        return redirect()->route('settings.profile')->with('success', __('settings.password_updated'));
    }
}
