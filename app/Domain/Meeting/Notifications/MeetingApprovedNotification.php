<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Notifications;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Infrastructure\Notifications\Messages\TeamsMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MinutesOfMeeting $meeting,
        public ?User $approvedBy,
        public ?MomCirculation $circulation = null,
    ) {}

    private function approverName(): string
    {
        return $this->approvedBy?->name
            ?? ($this->circulation
                ? "Pengesahan automatik · Pusingan {$this->circulation->round}"
                : 'Sistem');
    }

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
            ->subject(__('Meeting Approved: :title', ['title' => $this->meeting->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('The meeting **:title** has been approved by :approver.', ['title' => $this->meeting->title, 'approver' => $this->approverName()]))
            ->action(__('View Meeting'), route('meetings.show', $this->meeting));
    }

    public function toTeams(object $notifiable): TeamsMessage
    {
        return (new TeamsMessage)
            ->title(__('Meeting Approved'))
            ->content(__('The meeting **:title** has been approved by :name.', ['title' => $this->meeting->title, 'name' => $this->approverName()]))
            ->fact(__('Meeting'), $this->meeting->title)
            ->fact(__('Approved By'), $this->approverName())
            ->action(__('View Meeting'), route('meetings.show', $this->meeting));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'meeting_approved',
            'meeting_id' => $this->meeting->id,
            'title' => $this->meeting->title,
            'approved_by' => $this->approverName(),
            'message' => __('":title" was approved by :name', ['title' => $this->meeting->title, 'name' => $this->approverName()]),
        ];
    }
}
