<?php

declare(strict_types=1);

use App\Support\Enums\InputType;

test('input type has expected cases', function () {
    expect(InputType::cases())->toHaveCount(4);
    expect(InputType::Audio->value)->toBe('audio');
    expect(InputType::Document->value)->toBe('document');
    expect(InputType::ManualNote->value)->toBe('manual_note');
    expect(InputType::BrowserRecording->value)->toBe('browser_recording');
});
