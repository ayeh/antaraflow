<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Services\MobileAuthService;
use App\Support\Enums\DevicePlatform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends MobileController
{
    public function __construct(
        private readonly MobileAuthService $authService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:128'],
            'push_token' => ['sometimes', 'nullable', 'string', 'max:512'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:32'],
            'locale' => ['sometimes', 'nullable', 'string', 'max:8'],
        ]);

        $device = $this->authService->registerDevice(
            $this->user($request),
            $validated['device_id'],
            $validated['platform'],
            $validated['push_token'] ?? null,
            $validated['app_version'] ?? null,
            $validated['locale'] ?? null,
        );

        return response()->json([
            'device_id' => $device->device_id,
            'platform' => $device->platform->value,
            'push_enabled' => $device->push_token !== null,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, string $deviceId): JsonResponse
    {
        $this->authService->revokeDevice($this->user($request), $deviceId);

        return response()->json(null, 204);
    }
}
