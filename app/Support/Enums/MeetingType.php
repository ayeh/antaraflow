<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum MeetingType: string
{
    case General = 'general';
    case StandUp = 'standup';
    case Retrospective = 'retrospective';
    case ClientCall = 'client_call';
    case BoardMeeting = 'board_meeting';
    case OneOnOne = 'one_on_one';
    case Workshop = 'workshop';

    public function label(): string
    {
        return match ($this) {
            self::General => __('General'),
            self::StandUp => __('Stand-up'),
            self::Retrospective => __('Retrospective'),
            self::ClientCall => __('Client Call'),
            self::BoardMeeting => __('Board Meeting'),
            self::OneOnOne => '1-on-1',
            self::Workshop => __('Workshop'),
        };
    }
}
