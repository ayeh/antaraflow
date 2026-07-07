<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ResolutionStatus: string
{
    case Proposed = 'proposed';
    case Passed = 'passed';
    case Failed = 'failed';
    case Tabled = 'tabled';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Proposed => __('Proposed'),
            self::Passed => __('Passed'),
            self::Failed => __('Failed'),
            self::Tabled => __('Tabled'),
            self::Withdrawn => __('Withdrawn'),
        };
    }
}
