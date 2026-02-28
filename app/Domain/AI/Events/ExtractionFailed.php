<?php

declare(strict_types=1);

namespace App\Domain\AI\Events;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Events\Dispatchable;

class ExtractionFailed
{
    use Dispatchable;

    public function __construct(
        public readonly MinutesOfMeeting $meeting,
        public readonly string $error,
    ) {}
}
