<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Commands;

use App\Domain\Calendar\Models\CalendarConnection;
use App\Domain\Calendar\Notifications\CalendarMeetingStartingNotification;
use App\Domain\Calendar\Services\CalendarSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class NotifyUpcomingMeetingsCommand extends Command
{
    protected $signature = 'calendar:notify-upcoming {--window=10 : Minutes ahead to notify for}';

    protected $description = 'Notify users of calendar meetings starting soon (connections with "Notify on start" enabled)';

    public function __construct(
        private readonly CalendarSyncService $calendarSyncService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $from = now();
        $to = now()->addMinutes((int) $this->option('window'));

        $connections = CalendarConnection::query()
            ->where('is_active', true)
            ->where('auto_record', true)
            ->whereNotNull('refresh_token')
            ->with('user')
            ->get();

        $notified = 0;

        foreach ($connections as $connection) {
            if (! $connection->user) {
                continue;
            }

            try {
                $provider = $this->calendarSyncService->resolveProvider($connection->provider);

                if ($connection->isTokenExpired()) {
                    $connection = $provider->refreshToken($connection);
                }

                $events = $provider->listUpcomingEvents($connection, $from, $to);
            } catch (Throwable $e) {
                report($e);
                $this->error("Calendar sync failed for connection {$connection->id}: {$e->getMessage()}");

                continue;
            }

            foreach ($events as $event) {
                $cacheKey = "calendar-notify:{$connection->id}:{$event['id']}:{$event['start']->timestamp}";

                if (Cache::has($cacheKey)) {
                    continue;
                }

                $connection->user->notify(new CalendarMeetingStartingNotification(
                    $event['title'],
                    $event['start'],
                    ucfirst($connection->provider),
                ));

                Cache::put($cacheKey, true, now()->addHours(2));
                $notified++;
            }
        }

        $this->info("Dispatched {$notified} meeting-start notification(s) across {$connections->count()} connection(s).");

        return self::SUCCESS;
    }
}
