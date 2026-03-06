<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Listeners;

use App\Domain\AI\Events\ExtractionCompleted;
use App\Domain\Meeting\Events\MeetingApproved;
use App\Domain\Meeting\Events\MeetingFinalized;
use App\Domain\Transcription\Events\TranscriptionCompleted;
use App\Domain\Webhook\Services\WebhookDispatcher;
use App\Support\Enums\WebhookEvent;
use Illuminate\Events\Dispatcher;

class WebhookEventSubscriber
{
    public function __construct(
        private WebhookDispatcher $dispatcher,
    ) {}

    public function handleMeetingFinalized(MeetingFinalized $event): void
    {
        $meeting = $event->meeting;

        $this->dispatcher->dispatch($meeting->organization_id, WebhookEvent::MeetingFinalized->value, [
            'meeting_id' => $meeting->id,
            'title' => $meeting->title,
            'mom_number' => $meeting->mom_number,
            'meeting_date' => $meeting->meeting_date?->toIso8601String(),
            'status' => $meeting->status->value,
        ]);
    }

    public function handleMeetingApproved(MeetingApproved $event): void
    {
        $meeting = $event->meeting;

        $this->dispatcher->dispatch($meeting->organization_id, WebhookEvent::MeetingApproved->value, [
            'meeting_id' => $meeting->id,
            'title' => $meeting->title,
            'mom_number' => $meeting->mom_number,
            'status' => $meeting->status->value,
        ]);
    }

    public function handleTranscriptionCompleted(TranscriptionCompleted $event): void
    {
        $transcription = $event->transcription;

        $this->dispatcher->dispatch($transcription->minutesOfMeeting->organization_id, WebhookEvent::TranscriptionCompleted->value, [
            'transcription_id' => $transcription->id,
            'meeting_id' => $transcription->minutes_of_meeting_id,
            'status' => $transcription->status,
        ]);
    }

    public function handleExtractionCompleted(ExtractionCompleted $event): void
    {
        $meeting = $event->meeting;

        $this->dispatcher->dispatch($meeting->organization_id, WebhookEvent::ExtractionCompleted->value, [
            'meeting_id' => $meeting->id,
            'title' => $meeting->title,
        ]);
    }

    /** @return array<string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            MeetingFinalized::class => 'handleMeetingFinalized',
            MeetingApproved::class => 'handleMeetingApproved',
            TranscriptionCompleted::class => 'handleTranscriptionCompleted',
            ExtractionCompleted::class => 'handleExtractionCompleted',
        ];
    }
}
