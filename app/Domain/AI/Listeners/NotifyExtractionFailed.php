<?php

declare(strict_types=1);

namespace App\Domain\AI\Listeners;

use App\Domain\AI\Events\ExtractionFailed;
use App\Domain\AI\Notifications\ExtractionFailedNotification;
use App\Models\User;

class NotifyExtractionFailed
{
    public function handle(ExtractionFailed $event): void
    {
        $creator = User::find($event->meeting->created_by);

        $creator?->notify(new ExtractionFailedNotification($event->meeting, $event->error));
    }
}
