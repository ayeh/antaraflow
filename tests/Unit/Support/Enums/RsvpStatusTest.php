<?php

declare(strict_types=1);

use App\Support\Enums\RsvpStatus;

test('rsvp status has expected cases', function () {
    expect(RsvpStatus::cases())->toHaveCount(4);
    expect(RsvpStatus::Pending->value)->toBe('pending');
    expect(RsvpStatus::Accepted->value)->toBe('accepted');
    expect(RsvpStatus::Declined->value)->toBe('declined');
    expect(RsvpStatus::Tentative->value)->toBe('tentative');
});
