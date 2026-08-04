<?php

declare(strict_types=1);

use App\Domain\Meeting\Models\MomCirculation;

test('mom circulation has correct fillable attributes', function () {
    $circulation = new MomCirculation;

    expect($circulation->getFillable())->toContain('subject')
        ->toContain('body_note')
        ->toContain('deadline_at')
        ->toContain('status')
        ->toContain('round');
});

test('mom circulation status open is default', function () {
    $circulation = new MomCirculation(['status' => 'open']);
    expect($circulation->status)->toBe('open');
});
