<?php

declare(strict_types=1);

namespace App\Domain\AI\Listeners;

use App\Domain\AI\Events\ExtractionCompleted;
use App\Domain\AI\Jobs\BuildKnowledgeLinksJob;
use App\Domain\AI\Notifications\ExtractionCompletedNotification;
use App\Models\User;

class NotifyExtractionComplete
{
    public function handle(ExtractionCompleted $event): void
    {
        $creator = User::find($event->meeting->created_by);

        $creator?->notify(new ExtractionCompletedNotification($event->meeting));

        BuildKnowledgeLinksJob::dispatch($event->meeting);
    }
}
