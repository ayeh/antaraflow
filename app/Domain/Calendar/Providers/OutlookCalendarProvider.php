<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Providers;

use App\Domain\Calendar\Contracts\CalendarProviderInterface;
use App\Domain\Calendar\Models\CalendarConnection;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OutlookCalendarProvider implements CalendarProviderInterface
{
    private const GRAPH_API = 'https://graph.microsoft.com/v1.0';

    public function getAuthUrl(string $redirectUri, string $state): string
    {
        $tenant = config('calendar.outlook.tenant_id', 'common');

        $params = http_build_query([
            'client_id' => config('calendar.outlook.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'offline_access Calendars.ReadWrite',
            'state' => $state,
            'response_mode' => 'query',
        ]);

        return "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/authorize?{$params}";
    }

    /** @return array{access_token: string, refresh_token: ?string, expires_at: ?string} */
    public function handleCallback(string $code, string $redirectUri): array
    {
        $tenant = config('calendar.outlook.tenant_id', 'common');

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            [
                'client_id' => config('calendar.outlook.client_id'),
                'client_secret' => config('calendar.outlook.client_secret'),
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
                'scope' => 'offline_access Calendars.ReadWrite',
            ]
        );

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
        $tenant = config('calendar.outlook.tenant_id', 'common');

        $response = Http::asForm()->post(
            "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token",
            [
                'client_id' => config('calendar.outlook.client_id'),
                'client_secret' => config('calendar.outlook.client_secret'),
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
                'scope' => 'offline_access Calendars.ReadWrite',
            ]
        );

        $response->throw();
        $data = $response->json();

        $connection->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
            'token_expires_at' => isset($data['expires_in'])
                ? now()->addSeconds((int) $data['expires_in'])
                : null,
        ]);

        return $connection->fresh();
    }

    public function createEvent(CalendarConnection $connection, MinutesOfMeeting $meeting): string
    {
        $calendarId = $connection->calendar_id;
        $url = $calendarId
            ? self::GRAPH_API."/me/calendars/{$calendarId}/events"
            : self::GRAPH_API.'/me/events';

        $response = Http::withToken($connection->access_token)
            ->post($url, $this->buildEventData($meeting));

        $response->throw();

        return $response->json('id');
    }

    public function updateEvent(CalendarConnection $connection, MinutesOfMeeting $meeting): void
    {
        $eventId = $meeting->calendar_event_id;

        Http::withToken($connection->access_token)
            ->patch(self::GRAPH_API."/me/events/{$eventId}", $this->buildEventData($meeting))
            ->throw();
    }

    public function deleteEvent(CalendarConnection $connection, string $eventId): void
    {
        Http::withToken($connection->access_token)
            ->delete(self::GRAPH_API."/me/events/{$eventId}")
            ->throw();
    }

    /** @return array<int, array{id: string, name: string}> */
    public function listCalendars(CalendarConnection $connection): array
    {
        $response = Http::withToken($connection->access_token)
            ->get(self::GRAPH_API.'/me/calendars');

        $response->throw();

        return collect($response->json('value', []))
            ->map(fn (array $calendar): array => [
                'id' => $calendar['id'],
                'name' => $calendar['name'] ?? $calendar['id'],
            ])
            ->values()
            ->all();
    }

    public function registerWebhook(CalendarConnection $connection): void
    {
        $response = Http::withToken($connection->access_token)
            ->post(self::GRAPH_API.'/subscriptions', [
                'changeType' => 'created,updated,deleted',
                'notificationUrl' => route('calendar.webhook', 'outlook'),
                'resource' => '/me/events',
                'expirationDateTime' => now()->addDays(2)->toIso8601String(),
                'clientState' => Str::random(32),
            ]);

        $response->throw();
        $data = $response->json();

        $connection->update([
            'webhook_channel_id' => $data['id'] ?? null,
            'webhook_expiry' => isset($data['expirationDateTime'])
                ? now()->parse($data['expirationDateTime'])
                : null,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function handleWebhook(Request $request): array
    {
        $notifications = $request->input('value', []);

        return collect($notifications)
            ->map(fn (array $notification): array => [
                'change_type' => $notification['changeType'] ?? null,
                'resource' => $notification['resource'] ?? null,
                'subscription_id' => $notification['subscriptionId'] ?? null,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function buildEventData(MinutesOfMeeting $meeting): array
    {
        $data = [
            'subject' => $meeting->title,
            'body' => [
                'contentType' => 'text',
                'content' => $meeting->summary ?? '',
            ],
        ];

        if ($meeting->meeting_date) {
            $data['start'] = [
                'dateTime' => $meeting->meeting_date->format('Y-m-d\TH:i:s'),
                'timeZone' => 'UTC',
            ];
            $end = $meeting->duration_minutes
                ? $meeting->meeting_date->addMinutes($meeting->duration_minutes)
                : $meeting->meeting_date->addHour();
            $data['end'] = [
                'dateTime' => $end->format('Y-m-d\TH:i:s'),
                'timeZone' => 'UTC',
            ];
        }

        if ($meeting->location) {
            $data['location'] = ['displayName' => $meeting->location];
        }

        return $data;
    }
}
