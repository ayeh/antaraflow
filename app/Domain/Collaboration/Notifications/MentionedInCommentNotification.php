<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Notifications;

use App\Domain\Collaboration\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentionedInCommentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Comment $comment,
        public bool $sendEmail = true,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->sendEmail ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You were mentioned in a comment')
            ->line('You were mentioned in a comment by '.$this->comment->user->name.'.')
            ->action('View Comment', route('meetings.show', $this->comment->commentable_id));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mention',
            'comment_id' => $this->comment->id,
            'commenter' => $this->comment->user->name,
            'meeting_id' => $this->comment->commentable_id,
        ];
    }
}
