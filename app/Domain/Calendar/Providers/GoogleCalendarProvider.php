<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Providers;

use App\Domain\Calendar\Contracts\CalendarProviderInterface;
use App\Domain\Calendar\Models\CalendarConnection;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleCalendarProvider implements CalendarProviderInterface
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const CALENDAR_API = 'https://www.googleapis.com/calendar/v3';

    public function getAuthUrl(string $redirectUri, string $state): string
    {
        $params = http_build_query([
            'client_id' => config('calendar.google.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return self::AUTH_URL.'?'.$params;
    }

    /** @return array{access_token: string, refresh_token: ?string, expires_at: ?string} */
    public function handleCallback(string $code, string $redirectUri): array
    {
        $response = Http::post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('calendar.google.client_id'),
            'client_secret' => config('calendar.google.client_secret'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        $response->throw();
        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => isset($data['expires_in'])
                ? now()->addSeconds((int) $data['expires_in'])->toDateTimeString()
                : null,
        ];
    }

    public function refreshToken(CalendarConnection $connection): CalendarConnection
    {
        $response = Http::post(self::TOKEN_URL, [
            'client_id' => config('calendar.google.client_id'),
            'client_secret' => config('calendar.google.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        $response->throw();
        $data = $response->json();

        $connection->update([
            'access_token' => $data['access_token'],
            'token_expires_at' => isset($data['expires_in'])
                ? now()->addSeconds((int) $data['expires_in'])
                : null,
        ]);

        return $connection->fresh();
    }

    public function createEvent(CalendarConnection $connection, MinutesOfMeeting $meeting): string
    {
        $calendarId = $connection->calendar_id ?? 'primary';

        $response = Http::withToken($connection->access_token)
            ->post(self::CALENDAR_API."/calendars/{$calendarId}/events", $this->buildEventData($meeting));

        $response->throw();

        return $response->json('id');
    }

    public function updateEvent(CalendarConnection $connection, MinutesOfMeeting $meeting): void
    {
        $calendarId = $connection->calendar_id ?? 'primary';
        $eventId = $meeting->calendar_event_id;

        Http::withToken($connection->access_token)
            ->put(self::CALENDAR_API."/calendars/{$calendarId}/events/{$eventId}", $this->buildEventData($meeting))
            ->throw();
    }

    public function deleteEvent(CalendarConnection $connection, string $eventId): void
    {
        $calendarId = $connection->calendar_id ?? 'primary';

        Http::withToken($connection->access_token)
            ->delete(self::CALENDAR_API."/calendars/{$calendarId}/events/{$eventId}")
            ->throw();
    }

    /** @return array<int, array{id: string, name: string}> */
    public function listCalendars(CalendarConnection $connection): array
    {
        $response = Http::withToken($connection->access_token)
            ->get(self::CALENDAR_API.'/users/me/calendarList');

        $response->throw();

        return collect($response->json('items', []))
            ->map(fn (array $calendar): array => [
                'id' => $calendar['id'],
                'name' => $calendar['summary'] ?? $calendar['id'],
            ])
            ->values()
            ->all();
    }

    public function registerWebhook(CalendarConnection $connection): void
    {
        $calendarId = $connection->calendar_id ?? 'primary';
        $channelId = Str::uuid()->toString();

        $response = Http::withToken($connection->access_token)
            ->post(self::CALENDAR_API."/calendars/{$calendarId}/events/watch", [
                'id' => $channelId,
                'type' => 'web_hook',
                'address' => route('calendar.webhook', 'google'),
            ]);

        $response->throw();
        $data = $response->json();

        $connection->update([
            'webhook_channel_id' => $channelId,
            'webhook_expiry' => isset($data['expiration'])
                ? now()->addMilliseconds((int) $data['expiration'] - (int) (microtime(true) * 1000))
                : null,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function handleWebhook(Request $request): array
    {
        return [
            [
                'channel_id' => $request->header('X-Goog-Channel-ID'),
                'resource_id' => $request->header('X-Goog-Resource-ID'),
                'resource_state' => $request->header('X-Goog-Resource-State'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function buildEventData(MinutesOfMeeting $meeting): array
    {
        $data = [
            'summary' => $meeting->title,
            'description' => $meeting->summary ?? '',
        ];

        if ($meeting->meeting_date) {
            $data['start'] = ['dateTime' => $meeting->meeting_date->toIso8601String()];
            $end = $meeting->duration_minutes
                ? $meeting->meeting_date->addMinutes($meeting->duration_minutes)
                : $meeting->meeting_date->addHour();
            $data['end'] = ['dateTime' => $end->toIso8601String()];
        }

        if ($meeting->location) {
            $data['location'] = $meeting->location;
        }

        return $data;
    }
}
