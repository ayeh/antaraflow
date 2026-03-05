<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Services;

use App\Domain\Calendar\Contracts\CalendarProviderInterface;
use App\Domain\Calendar\Models\CalendarConnection;
use App\Domain\Calendar\Providers\GoogleCalendarProvider;
use App\Domain\Calendar\Providers\OutlookCalendarProvider;
use App\Domain\Meeting\Models\MinutesOfMeeting;

class CalendarSyncService
{
    public function syncToCalendar(MinutesOfMeeting $meeting): void
    {
        $connections = CalendarConnection::query()
            ->where('organization_id', $meeting->organization_id)
            ->where('is_active', true)
            ->get();

        foreach ($connections as $connection) {
            $provider = $this->resolveProvider($connection->provider);

            if ($connection->isTokenExpired()) {
                $connection = $provider->refreshToken($connection);
            }

            if ($meeting->calendar_event_id && $meeting->calendar_provider === $connection->provider) {
                $provider->updateEvent($connection, $meeting);
            } else {
                $eventId = $provider->createEvent($connection, $meeting);
                $meeting->update([
                    'calendar_event_id' => $eventId,
                    'calendar_provider' => $connection->provider,
                    'calendar_synced_at' => now(),
                ]);
            }
        }
    }

    public function deleteFromCalendar(MinutesOfMeeting $meeting): void
    {
        if (! $meeting->calendar_event_id || ! $meeting->calendar_provider) {
            return;
        }

        $connection = CalendarConnection::query()
            ->where('organization_id', $meeting->organization_id)
            ->where('provider', $meeting->calendar_provider)
            ->where('is_active', true)
            ->first();

        if (! $connection) {
            return;
        }

        $provider = $this->resolveProvider($connection->provider);

        if ($connection->isTokenExpired()) {
            $connection = $provider->refreshToken($connection);
        }

        $provider->deleteEvent($connection, $meeting->calendar_event_id);
    }

    public function resolveProvider(string $provider): CalendarProviderInterface
    {
        return match ($provider) {
            'google' => app(GoogleCalendarProvider::class),
            'outlook' => app(OutlookCalendarProvider::class),
            default => throw new \InvalidArgumentException("Unknown calendar provider: {$provider}"),
        };
    }
}
