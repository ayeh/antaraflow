<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Export\Models\MomEmailDistribution;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create an email distribution record', function (): void {
    $org = Organization::factory()->create();
    $meeting = MinutesOfMeeting::factory()->for($org)->create();
    $user = User::factory()->create();

    $distribution = MomEmailDistribution::create([
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'recipients' => ['test@example.com', 'other@example.com'],
        'subject' => 'Meeting Minutes',
    ]);

    $distribution->refresh();

    expect($distribution->status)->toBe('pending');
    expect($distribution->recipients)->toBeArray();
});
