<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Notifications;

use App\Domain\Collaboration\Models\Comment;
use App\Infrastructure\Notifications\Push\PushMessage;
use App\Support\Traits\SendsMobilePush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentionedInCommentNotification extends Notification
{
    use Queueable, SendsMobilePush;

    public function __construct(
        public Comment $comment,
        public bool $sendEmail = true,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = $this->sendEmail ? ['database', 'mail'] : ['database'];

        return $this->withPush($notifiable, $channels);
    }

    public function toPush(object $notifiable): PushMessage
    {
        return new PushMessage(
            title: __('You were mentioned'),
            body: str((string) $this->comment->body)->stripTags()->limit(120)->toString(),
            deepLink: "antaraflow://meetings/{$this->comment->commentable_id}",
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You were mentioned in a comment'))
            ->line(__('You were mentioned in a comment by :name.', ['name' => $this->comment->user->name]))
            ->action(__('View Comment'), route('meetings.show', $this->comment->commentable_id));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mention',
            'comment_id' => $this->comment->id,
            'commenter' => $this->comment->user->name,
            'meeting_id' => $this->comment->commentable_id,
            'message' => __(':name mentioned you in a comment', ['name' => $this->comment->user->name]),
            'deep_link' => "antaraflow://meetings/{$this->comment->commentable_id}",
        ];
    }
}
