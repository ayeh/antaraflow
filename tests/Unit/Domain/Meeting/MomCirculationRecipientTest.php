<?php

declare(strict_types=1);

use App\Domain\Meeting\Models\MomCirculationRecipient;

test('recipient has correct fillable attributes', function () {
    $recipient = new MomCirculationRecipient;

    expect($recipient->getFillable())
        ->toContain('name')
        ->toContain('email')
        ->toContain('token')
        ->toContain('response');
});

test('recipient has no response by default', function () {
    $recipient = new MomCirculationRecipient(['response' => null]);
    expect($recipient->response)->toBeNull();
});

test('recipient can be confirmed', function () {
    $recipient = new MomCirculationRecipient(['response' => 'confirmed']);
    expect($recipient->response)->toBe('confirmed');
});

test('recipient tracks open count', function () {
    $recipient = new MomCirculationRecipient(['open_count' => 3]);
    expect($recipient->open_count)->toBe(3);
});
