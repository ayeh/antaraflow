<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ExtractionType: string
{
    case Summary = 'summary';
    case ActionItems = 'action_items';
    case Decisions = 'decisions';
    case Topics = 'topics';
    case Risks = 'risks';
    case FollowUpEmail = 'follow_up_email';
    case MeetingPreparation = 'meeting_preparation';
    case AgendaEmail = 'agenda_email';

    public function label(): string
    {
        return match ($this) {
            self::Summary => __('Summary'),
            self::ActionItems => __('Action Items'),
            self::Decisions => __('Decisions'),
            self::Topics => __('Topics'),
            self::Risks => __('Risks'),
            self::FollowUpEmail => __('Follow-up Email'),
            self::MeetingPreparation => __('Meeting Preparation'),
            self::AgendaEmail => __('Agenda Email'),
        };
    }
}
