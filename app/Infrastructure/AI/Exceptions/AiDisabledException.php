<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Exceptions;

use RuntimeException;

class AiDisabledException extends RuntimeException
{
    public static function make(): self
    {
        return new self('AI features are currently disabled by the platform administrator.');
    }
}
