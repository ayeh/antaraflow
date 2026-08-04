<?php

declare(strict_types=1);

use App\Domain\Meeting\Events\MeetingApproved;
use App\Models\User;

test('MeetingApproved can be constructed with null approvedBy', function () {
    $event = new MeetingApproved(approvedBy: null);
    expect($event->approvedBy)->toBeNull();
});

test('MeetingApproved can be constructed with a user', function () {
    $user = new User(['name' => 'Test User']);
    $event = new MeetingApproved(approvedBy: $user);
    expect($event->approvedBy)->toBe($user);
});

test('MeetingApproved accepts an optional circulation', function () {
    $event = new MeetingApproved(approvedBy: null, circulation: null);
    expect($event->circulation)->toBeNull();
});
