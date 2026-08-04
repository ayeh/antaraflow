<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Events;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class MeetingApproved
{
    use Dispatchable;

    public function __construct(
        public readonly ?MinutesOfMeeting $meeting = null,
        public readonly ?User $approvedBy = null,
        public readonly ?MomCirculation $circulation = null,
    ) {}
}
