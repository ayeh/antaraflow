<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Notifications;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\Notifications\Messages\TeamsMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingFinalizedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MinutesOfMeeting $meeting,
        public User $finalizedBy,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail'];

        if ($notifiable->currentOrganization?->hasTeamsWebhook()) {
            $channels[] = 'teams';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Meeting Finalized: :title', ['title' => $this->meeting->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('The meeting **:title** has been finalized by :finalizer.', ['title' => $this->meeting->title, 'finalizer' => $this->finalizedBy->name]))
            ->line(__('Please review and take action on your assigned items.'))
            ->action(__('View Meeting'), route('meetings.show', $this->meeting));
    }

    public function toTeams(object $notifiable): TeamsMessage
    {
        return (new TeamsMessage)
            ->title(__('Meeting Finalized'))
            ->content(__('The meeting **:title** has been finalized by :name.', ['title' => $this->meeting->title, 'name' => $this->finalizedBy->name]))
            ->fact(__('Meeting'), $this->meeting->title)
            ->fact(__('Finalized By'), $this->finalizedBy->name)
            ->action(__('View Meeting'), route('meetings.show', $this->meeting));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'meeting_finalized',
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'finalized_by' => $this->finalizedBy->name,
            'message' => __('":title" was finalized by :name', ['title' => $this->meeting->title, 'name' => $this->finalizedBy->name]),
        ];
    }
}
