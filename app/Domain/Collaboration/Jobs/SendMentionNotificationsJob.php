<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Jobs;

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\MomMention;
use App\Domain\Collaboration\Notifications\MentionedInCommentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMentionNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Comment $comment) {}

    public function handle(): void
    {
        $mentions = MomMention::query()
            ->where('comment_id', $this->comment->id)
            ->whereNull('notified_at')
            ->with('mentionedUser')
            ->get();

        foreach ($mentions as $mention) {
            $mention->mentionedUser->notify(
                new MentionedInCommentNotification($this->comment)
            );

            $mention->update(['notified_at' => now()]);
        }
    }
}
