<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum WebhookEvent: string
{
    case MeetingCreated = 'meeting.created';
    case MeetingFinalized = 'meeting.finalized';
    case MeetingApproved = 'meeting.approved';
    case TranscriptionCompleted = 'transcription.completed';
    case ExtractionCompleted = 'extraction.completed';
    case ActionItemCreated = 'action_item.created';

    public function label(): string
    {
        return match ($this) {
            self::MeetingCreated => 'Meeting Created',
            self::MeetingFinalized => 'Meeting Finalized',
            self::MeetingApproved => 'Meeting Approved',
            self::TranscriptionCompleted => 'Transcription Completed',
            self::ExtractionCompleted => 'Extraction Completed',
            self::ActionItemCreated => 'Action Item Created',
        };
    }
}
