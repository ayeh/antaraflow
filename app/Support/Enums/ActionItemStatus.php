<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ActionItemStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case CarriedForward = 'carried_forward';
}
