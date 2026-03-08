<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Enums;

enum LiveSessionStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';
}
