<?php

declare(strict_types=1);

use App\Domain\Collaboration\Models\Comment;

test('comment user_id is in fillable and accepts null', function () {
    $comment = new Comment(['user_id' => null]);
    expect($comment->user_id)->toBeNull();
});

test('comment can reference a circulation recipient', function () {
    $comment = new Comment(['mom_circulation_recipient_id' => 1]);
    expect($comment->mom_circulation_recipient_id)->toBe(1);
});

test('comment has circulationRecipient relationship', function () {
    $comment = new Comment;
    expect(method_exists($comment, 'circulationRecipient'))->toBeTrue();
});
