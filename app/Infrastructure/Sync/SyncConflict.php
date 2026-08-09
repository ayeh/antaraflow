<?php

declare(strict_types=1);

namespace App\Infrastructure\Sync;

use RuntimeException;

/**
 * Raised when a queued offline change was written against a version of the
 * record the server has since moved past. The client is handed the current
 * server state so a person can decide, rather than having their edit silently
 * win or silently vanish.
 */
class SyncConflict extends RuntimeException
{
    /** @param array<string, mixed> $serverState */
    public function __construct(
        public readonly string $reason,
        public readonly array $serverState = [],
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
