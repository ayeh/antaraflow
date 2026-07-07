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
            self::MeetingCreated => __('Meeting Created'),
            self::MeetingFinalized => __('Meeting Finalized'),
            self::MeetingApproved => __('Meeting Approved'),
            self::TranscriptionCompleted => __('Transcription Completed'),
            self::ExtractionCompleted => __('Extraction Completed'),
            self::ActionItemCreated => __('Action Item Created'),
        };
    }
}
