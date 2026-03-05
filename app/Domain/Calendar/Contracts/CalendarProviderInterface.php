<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Contracts;

use App\Domain\Calendar\Models\CalendarConnection;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\Request;

interface CalendarProviderInterface
{
    public function getAuthUrl(string $redirectUri, string $state): string;

    /** @return array{access_token: string, refresh_token: ?string, expires_at: ?string} */
    public function handleCallback(string $code, string $redirectUri): array;

    public function refreshToken(CalendarConnection $connection): CalendarConnection;

    public function createEvent(CalendarConnection $connection, MinutesOfMeeting $meeting): string;

    public function updateEvent(CalendarConnection $connection, MinutesOfMeeting $meeting): void;

    public function deleteEvent(CalendarConnection $connection, string $eventId): void;

    /** @return array<int, array{id: string, name: string}> */
    public function listCalendars(CalendarConnection $connection): array;

    public function registerWebhook(CalendarConnection $connection): void;

    /** @return array<int, array<string, mixed>> */
    public function handleWebhook(Request $request): array;
}
