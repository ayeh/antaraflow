<?php

declare(strict_types=1);

namespace App\Infrastructure\Sync;

use Illuminate\Support\Carbon;

/**
 * Position in the change stream, as (updated_at, id).
 *
 * The id breaks ties: several rows can share a timestamp to the second, and
 * paging on the timestamp alone would either skip them or repeat them forever.
 */
class SyncCursor
{
    public function __construct(
        public readonly Carbon $since,
        public readonly int $lastId = 0,
    ) {}

    public static function decode(?string $encoded): ?self
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }

        $decoded = json_decode((string) base64_decode($encoded, true), true);

        if (! is_array($decoded) || ! isset($decoded['t'])) {
            return null;
        }

        try {
            return new self(Carbon::parse($decoded['t']), (int) ($decoded['i'] ?? 0));
        } catch (\Throwable) {
            return null;
        }
    }

    public function encode(): string
    {
        return base64_encode((string) json_encode([
            't' => $this->since->toIso8601String(),
            'i' => $this->lastId,
        ]));
    }
}
