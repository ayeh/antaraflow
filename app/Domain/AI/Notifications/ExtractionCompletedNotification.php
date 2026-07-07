<?php

declare(strict_types=1);

namespace App\Domain\AI\Notifications;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\Notifications\Messages\TeamsMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExtractionCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MinutesOfMeeting $meeting,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->currentOrganization?->hasTeamsWebhook()) {
            $channels[] = 'teams';
        }

        return $channels;
    }

    public function toTeams(object $notifiable): TeamsMessage
    {
        return (new TeamsMessage)
            ->title(__('AI Extraction Completed'))
            ->content(__('AI extraction has been completed for **:title**. Summary, action items, and decisions are now available.', ['title' => $this->meeting->title]))
            ->fact(__('Meeting'), $this->meeting->title)
            ->action(__('View Extractions'), route('meetings.show', $this->meeting));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'extraction_completed',
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'message' => __('AI extraction completed for ":title"', ['title' => $this->meeting->title]),
        ];
    }
}
