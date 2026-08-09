<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\Push;

use App\Domain\Account\Models\UserDevice;

interface PushSender
{
    /**
     * Deliver a message to one device.
     *
     * Returns false when the device token is dead and should be forgotten, so
     * the caller can prune it instead of retrying forever.
     */
    public function send(UserDevice $device, PushMessage $message): bool;
}
