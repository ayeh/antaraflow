<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum AttendeeRole: string
{
    case Organizer = 'organizer';
    case Presenter = 'presenter';
    case NoteTaker = 'note_taker';
    case Participant = 'participant';
    case Observer = 'observer';
}
