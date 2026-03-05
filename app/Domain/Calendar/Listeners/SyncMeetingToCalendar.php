<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Listeners;

use App\Domain\Calendar\Services\CalendarSyncService;
use App\Domain\Meeting\Events\MeetingFinalized;
use Illuminate\Contracts\Queue\ShouldQueue;

class SyncMeetingToCalendar implements ShouldQueue
{
    public function __construct(
        private readonly CalendarSyncService $calendarSyncService,
    ) {}

    public function handle(MeetingFinalized $event): void
    {
        $this->calendarSyncService->syncToCalendar($event->meeting);
    }
}
