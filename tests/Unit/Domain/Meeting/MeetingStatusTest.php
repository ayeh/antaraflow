<?php

declare(strict_types=1);

use App\Support\Enums\MeetingStatus;

test('pending confirmation sits between finalized and approved', function () {
    expect(MeetingStatus::PendingConfirmation->value)->toBe('pending_confirmation');
    expect(MeetingStatus::tryFrom('pending_confirmation'))->toBe(MeetingStatus::PendingConfirmation);
});
