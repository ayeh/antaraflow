<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\Channels;

use App\Domain\Account\Models\UserDevice;
use App\Infrastructure\Notifications\Push\PushMessage;
use App\Infrastructure\Notifications\Push\PushSender;
use Illuminate\Notifications\Notification;

/**
 * Fans a notification out to every live device a person has registered.
 *
 * A notification opts in by defining toPush(); everything else is ignored, so
 * adding 'push' to via() on a class that has not been given a mobile payload is
 * harmless rather than fatal.
 */
class PushChannel
{
    public function __construct(
        private readonly PushSender $sender,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toPush')) {
            return;
        }

        if (! method_exists($notifiable, 'devices')) {
            return;
        }

        $message = $notification->toPush($notifiable);

        if (! $message instanceof PushMessage) {
            return;
        }

        $devices = $notifiable->devices()->active()->get();

        foreach ($devices as $device) {
            /** @var UserDevice $device */
            $delivered = $this->sender->send($device, $message);

            if (! $delivered) {
                $device->update(['push_token' => null, 'revoked_at' => now()]);
            }
        }
    }
}
