<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Services;

use App\Support\Enums\MeetingPlatform;

class MeetingLinkDetector
{
    public static function detect(?string $url): ?MeetingPlatform
    {
        if (! $url) {
            return null;
        }

        return match (true) {
            str_contains($url, 'zoom.us') => MeetingPlatform::Zoom,
            str_contains($url, 'meet.google.com') => MeetingPlatform::GoogleMeet,
            str_contains($url, 'teams.microsoft.com'), str_contains($url, 'teams.live.com') => MeetingPlatform::MicrosoftTeams,
            default => MeetingPlatform::Other,
        };
    }
}
