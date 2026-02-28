<?php

declare(strict_types=1);

use App\Support\Enums\UserRole;

test('user role has expected cases', function () {
    expect(UserRole::cases())->toHaveCount(5);
    expect(UserRole::Owner->value)->toBe('owner');
    expect(UserRole::Admin->value)->toBe('admin');
    expect(UserRole::Manager->value)->toBe('manager');
    expect(UserRole::Member->value)->toBe('member');
    expect(UserRole::Viewer->value)->toBe('viewer');
});
