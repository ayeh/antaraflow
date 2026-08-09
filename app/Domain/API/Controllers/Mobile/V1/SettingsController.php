<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

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
        $settings = $this->user($request)->settings;

        return response()->json([
            'data' => $settings?->notification_preferences ?? $this->defaultPreferences(),
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

        $settings = $user->settings()->firstOrCreate([]);
        $settings->update([
            'notification_preferences' => array_merge(
                $settings->notification_preferences ?? $this->defaultPreferences(),
                $validated['preferences'],
            ),
        ]);

        return response()->json(['data' => $settings->fresh()->notification_preferences]);
    }

    /** @return array<string, array<string, bool>> */
    private function defaultPreferences(): array
    {
        return [
            'action_item_assigned' => ['push' => true, 'email' => true],
            'meeting_finalized' => ['push' => true, 'email' => true],
            'meeting_approved' => ['push' => true, 'email' => true],
            'transcription_completed' => ['push' => true, 'email' => false],
            'mention' => ['push' => true, 'email' => true],
            'circulation_pending' => ['push' => true, 'email' => true],
        ];
    }
}
