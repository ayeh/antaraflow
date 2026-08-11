<?php

declare(strict_types=1);

namespace App\Domain\API\Services;

use App\Domain\Account\Models\UserDevice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\NewAccessToken;

class MobileAuthService
{
    /**
     * Abilities granted to a phone. Deliberately narrower than the web session:
     * organisation administration, billing and API-key management stay on the
     * desktop, so a stolen device cannot be used to change who has access.
     *
     * @var array<int, string>
     */
    public const ABILITIES = [
        'meetings:read',
        'meetings:write',
        'recordings:write',
        'action-items:write',
        'comments:write',
        'votes:cast',
        'approvals:submit',
        'ai:use',
    ];

    /**
     * One token per device: signing in again from the same phone replaces the
     * previous token instead of leaving a usable one behind.
     */
    public function issueToken(User $user, string $deviceId, string $deviceName): NewAccessToken
    {
        $user->tokens()->where('name', $this->tokenName($deviceId, $deviceName))->delete();

        return $user->createToken(
            $this->tokenName($deviceId, $deviceName),
            self::ABILITIES,
            $this->expiresAt(),
        );
    }

    public function registerDevice(
        User $user,
        string $deviceId,
        string $platform,
        ?string $pushToken = null,
        ?string $appVersion = null,
        ?string $locale = null,
    ): UserDevice {
        $attributes = [
            'platform' => $platform,
            'last_seen_at' => now(),
            'revoked_at' => null,
        ];

        foreach (['push_token' => $pushToken, 'app_version' => $appVersion, 'locale' => $locale] as $key => $value) {
            if ($value !== null) {
                $attributes[$key] = $value;
            }
        }

        /** @var UserDevice $device */
        $device = $user->devices()->updateOrCreate(['device_id' => $deviceId], $attributes);

        return $device;
    }

    public function revokeDevice(User $user, string $deviceId): void
    {
        $user->devices()->where('device_id', $deviceId)->update([
            'push_token' => null,
            'revoked_at' => now(),
        ]);
    }

    public function expiresAt(): ?Carbon
    {
        $days = (int) config('sanctum.mobile_expiration_days', 90);

        return $days > 0 ? now()->addDays($days) : null;
    }

    private function tokenName(string $deviceId, string $deviceName): string
    {
        return sprintf('mobile:%s:%s', $deviceId, $deviceName);
    }
}
