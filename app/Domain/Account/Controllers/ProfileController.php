<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Requests\UpdatePasswordRequest;
use App\Domain\Account\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $preferences = $user->preferences ?? [];
        if (isset($data['preferences'])) {
            $preferences = array_merge($preferences, $data['preferences']);
            unset($data['preferences']);
        }
        $data['preferences'] = $preferences;

        $user->update($data);

        return redirect()->route('profile.edit')->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return redirect()->route('profile.edit')->with('success', 'Password updated successfully.');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.theme' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['light', 'dark', 'system'])],
            'preferences.default_meeting_duration' => ['nullable', 'integer', 'min:5', 'max:480'],
            'preferences.notifications' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        $preferences = array_merge($user->preferences ?? [], $data['preferences']);
        $user->update(['preferences' => $preferences]);

        return redirect()->route('profile.edit')->with('success', 'Preferences updated successfully.');
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

        return redirect()->route('profile.edit')->with('success', 'Avatar updated successfully.');
    }
}
