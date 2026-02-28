<?php

declare(strict_types=1);

use App\Support\Enums\ActionItemStatus;

test('action item status has expected cases', function () {
    expect(ActionItemStatus::cases())->toHaveCount(5);
    expect(ActionItemStatus::Open->value)->toBe('open');
    expect(ActionItemStatus::InProgress->value)->toBe('in_progress');
    expect(ActionItemStatus::Completed->value)->toBe('completed');
    expect(ActionItemStatus::Cancelled->value)->toBe('cancelled');
    expect(ActionItemStatus::CarriedForward->value)->toBe('carried_forward');
});
