<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ActionItemPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';
}
