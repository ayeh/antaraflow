<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use Exception;

class LimitExceededException extends Exception
{
    public function __construct(
        public readonly string $metric,
        public readonly int $currentUsage,
        public readonly int $limit,
        public readonly string $planName,
    ) {
        parent::__construct(
            "You have reached the {$metric} limit ({$currentUsage}/{$limit}) on your {$planName} plan. Please upgrade to continue."
        );
    }
}
