<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Events;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class MeetingFinalized
{
    use Dispatchable;

    public function __construct(
        public readonly MinutesOfMeeting $meeting,
        public readonly User $finalizedBy,
    ) {}
}
