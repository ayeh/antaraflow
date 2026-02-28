<?php

declare(strict_types=1);

use App\Support\Enums\AttendeeRole;

test('attendee role has expected cases', function () {
    expect(AttendeeRole::cases())->toHaveCount(5);
    expect(AttendeeRole::Organizer->value)->toBe('organizer');
    expect(AttendeeRole::Presenter->value)->toBe('presenter');
    expect(AttendeeRole::NoteTaker->value)->toBe('note_taker');
    expect(AttendeeRole::Participant->value)->toBe('participant');
    expect(AttendeeRole::Observer->value)->toBe('observer');
});
