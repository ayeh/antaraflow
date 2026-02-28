<?php

declare(strict_types=1);

use App\Support\Enums\TranscriptionStatus;

test('transcription status has expected cases', function () {
    expect(TranscriptionStatus::cases())->toHaveCount(4);
    expect(TranscriptionStatus::Pending->value)->toBe('pending');
    expect(TranscriptionStatus::Processing->value)->toBe('processing');
    expect(TranscriptionStatus::Completed->value)->toBe('completed');
    expect(TranscriptionStatus::Failed->value)->toBe('failed');
});
