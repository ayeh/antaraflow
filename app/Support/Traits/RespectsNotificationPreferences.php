<?php

declare(strict_types=1);

namespace App\Support\Traits;

use App\Domain\Account\Support\NotificationPreferences;
use App\Models\User;

/**
 * Drops the channels the recipient has switched off.
 *
 * Until this existed the preference screen was decorative: every `via()`
 * returned a hardcoded channel list, and `notification_preferences` was read
 * by nothing but the two settings controllers that wrote it.
 *
 * `database` always survives. The in-app list is the record of what happened,
 * and somebody silencing push wants a quieter phone, not a hole in the
 * history.
 */
trait RespectsNotificationPreferences
{
    /**
     * The key in the preference document this notification answers to.
     *
     * Notifications that are operational rather than personal — budget
     * alerts, anything for an administrator — should not use this trait at
     * all instead of returning a key nobody can see.
     */
    abstract protected function preferenceKey(): string;

    /**
     * @param  array<int, string>  $channels
     * @return array<int, string>
     */
    protected function preferred(object $notifiable, array $channels): array
    {
        if (! $notifiable instanceof User) {
            return $channels;
        }

        $key = $this->preferenceKey();

        return array_values(array_filter(
            $channels,
            fn (string $channel) => match ($channel) {
                'mail' => NotificationPreferences::allows($notifiable, $key, 'email'),
                'push' => NotificationPreferences::allows($notifiable, $key, 'push'),
                default => true,
            },
        ));
    }
}
