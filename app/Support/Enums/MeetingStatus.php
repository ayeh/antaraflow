<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum MeetingStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Finalized = 'finalized';
    case Approved = 'approved';
}
