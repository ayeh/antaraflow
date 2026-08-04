<?php

declare(strict_types=1);

use App\Support\Enums\MeetingStatus;

test('meeting status has expected cases', function () {
    expect(MeetingStatus::cases())->toHaveCount(5);
    expect(MeetingStatus::Draft->value)->toBe('draft');
    expect(MeetingStatus::InProgress->value)->toBe('in_progress');
    expect(MeetingStatus::Finalized->value)->toBe('finalized');
    expect(MeetingStatus::PendingConfirmation->value)->toBe('pending_confirmation');
    expect(MeetingStatus::Approved->value)->toBe('approved');
});
