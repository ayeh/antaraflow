<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\Push;

/**
 * One push notification, independent of the transport that delivers it.
 *
 * `deepLink` is carried in the data payload rather than the visible body so a
 * tap opens the right screen; without it every notification lands the user on
 * the home tab and they have to find the thing again by hand.
 */
class PushMessage
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $deepLink = null,
        public readonly ?string $notificationId = null,
        /** @var array<string, string> */
        public readonly array $data = [],
        public readonly ?int $badge = null,
    ) {}

    /** @return array<string, string> */
    public function dataPayload(): array
    {
        return array_filter([
            'deep_link' => $this->deepLink,
            'notification_id' => $this->notificationId,
            ...$this->data,
        ], fn (?string $value) => $value !== null && $value !== '');
    }
}
