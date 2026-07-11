<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Exceptions;

use RuntimeException;

class OrgBudgetExceededException extends RuntimeException
{
    public static function make(): self
    {
        return new self('This organization has reached its AI usage budget. Contact your administrator.');
    }
}
