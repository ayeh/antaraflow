<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\Account\Support\NotificationPreferences;
use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Resources\Mobile\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends MobileController
{
    public function profile(Request $request): JsonResponse
    {
        return response()->json(new UserResource($this->user($request)));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'timezone' => ['sometimes', 'nullable', 'timezone'],
            'language' => ['sometimes', Rule::in(['en', 'ms'])],
        ]);

        $user->update($validated);

        return response()->json(new UserResource($user->fresh()));
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $request->validate([
            'avatar' => ['required', 'image', 'max:4096'],
        ]);

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return response()->json(new UserResource($user->fresh()));
    }

    public function notificationPreferences(Request $request): JsonResponse
    {
        return response()->json([
            'data' => NotificationPreferences::for($this->user($request)),
        ]);
    }

    public function updateNotificationPreferences(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['array'],
            'preferences.*.push' => ['sometimes', 'boolean'],
            'preferences.*.email' => ['sometimes', 'boolean'],
        ]);

        // Merged per channel, not per key. array_merge replaced whole entries,
        // so a client that only knows about push blanked out email — and the
        // web screen, which only knows about email, blanked out push.
        $merged = NotificationPreferences::merge($user, $validated['preferences']);

        $user->settings()->firstOrCreate([])->update([
            'notification_preferences' => $merged,
        ]);

        return response()->json(['data' => $merged]);
    }
}
