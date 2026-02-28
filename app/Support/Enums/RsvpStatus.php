<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum RsvpStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Tentative = 'tentative';
}
