<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Enums;

enum ChunkStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
