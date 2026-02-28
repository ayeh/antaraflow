<?php

declare(strict_types=1);

use App\Support\Enums\ActionItemPriority;

test('action item priority has expected cases', function () {
    expect(ActionItemPriority::cases())->toHaveCount(4);
    expect(ActionItemPriority::Low->value)->toBe('low');
    expect(ActionItemPriority::Medium->value)->toBe('medium');
    expect(ActionItemPriority::High->value)->toBe('high');
    expect(ActionItemPriority::Critical->value)->toBe('critical');
});
