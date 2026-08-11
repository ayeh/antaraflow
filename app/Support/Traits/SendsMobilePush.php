<?php

declare(strict_types=1);

namespace App\Support\Traits;

/**
 * Adds the push channel to a notification, but only when there is somewhere to
 * send it. Registering the channel unconditionally would queue work for the
 * large majority of users who have never installed the app.
 */
trait SendsMobilePush
{
    /**
     * @param  array<int, string>  $channels
     * @return array<int, string>
     */
    protected function withPush(object $notifiable, array $channels): array
    {
        if (! method_exists($notifiable, 'devices')) {
            return $channels;
        }

        if (! $notifiable->devices()->active()->exists()) {
            return $channels;
        }

        return [...$channels, 'push'];
    }
}
