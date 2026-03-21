<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\MomReaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('enforces unique emoji per user per comment', function (): void {
    $user = User::factory()->create();
    $org = \App\Domain\Account\Models\Organization::factory()->create();
    $comment = Comment::factory()->create(['organization_id' => $org->id, 'user_id' => $user->id]);

    MomReaction::create(['comment_id' => $comment->id, 'user_id' => $user->id, 'emoji' => '👍']);

    expect(fn () => MomReaction::create(['comment_id' => $comment->id, 'user_id' => $user->id, 'emoji' => '👍']))
        ->toThrow(UniqueConstraintViolationException::class);
});
