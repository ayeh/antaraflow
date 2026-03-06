<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum MeetingPlatform: string
{
    case Zoom = 'zoom';
    case GoogleMeet = 'google_meet';
    case MicrosoftTeams = 'microsoft_teams';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Zoom => 'Zoom',
            self::GoogleMeet => 'Google Meet',
            self::MicrosoftTeams => 'Microsoft Teams',
            self::Other => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Zoom => 'video',
            self::GoogleMeet => 'video',
            self::MicrosoftTeams => 'users',
            self::Other => 'link',
        };
    }
}
