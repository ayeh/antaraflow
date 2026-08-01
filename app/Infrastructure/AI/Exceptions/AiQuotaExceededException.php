<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Exceptions;

use RuntimeException;

/**
 * The provider refused the call because the account is out of quota or is being
 * rate limited. Distinct from OrgBudgetExceededException, which is our own
 * spend limit — this one originates upstream and no retry will clear it until
 * the provider's own window resets.
 */
class AiQuotaExceededException extends RuntimeException
{
    public static function make(string $providerMessage): self
    {
        return new self($providerMessage);
    }
}
