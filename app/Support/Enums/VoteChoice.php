<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum VoteChoice: string
{
    case For = 'for';
    case Against = 'against';
    case Abstain = 'abstain';

    public function label(): string
    {
        return match ($this) {
            self::For => __('For'),
            self::Against => __('Against'),
            self::Abstain => __('Abstain'),
        };
    }
}
