<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\MomMention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create a mention record', function (): void {
    $user = User::factory()->create();
    $mentionedUser = User::factory()->create();
    $org = \App\Domain\Account\Models\Organization::factory()->create();
    $meeting = \App\Domain\Meeting\Models\MinutesOfMeeting::factory()->for($org)->create();
    $comment = Comment::factory()->create(['organization_id' => $org->id, 'user_id' => $user->id]);

    $mention = MomMention::create([
        'comment_id' => $comment->id,
        'mentioned_user_id' => $mentionedUser->id,
        'organization_id' => $org->id,
        'minutes_of_meeting_id' => $meeting->id,
        'is_read' => false,
    ]);

    expect($mention->is_read)->toBeFalse();
    expect(MomMention::count())->toBe(1);
});
