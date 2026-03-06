<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ExtractionType: string
{
    case Summary = 'summary';
    case ActionItems = 'action_items';
    case Decisions = 'decisions';
    case Topics = 'topics';
    case FollowUpEmail = 'follow_up_email';

    public function label(): string
    {
        return match ($this) {
            self::Summary => 'Summary',
            self::ActionItems => 'Action Items',
            self::Decisions => 'Decisions',
            self::Topics => 'Topics',
            self::FollowUpEmail => 'Follow-up Email',
        };
    }
}
