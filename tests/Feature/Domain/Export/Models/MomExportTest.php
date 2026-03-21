<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Export\Models\MomExport;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can create an export record', function (): void {
    $org = Organization::factory()->create();
    $meeting = MinutesOfMeeting::factory()->for($org)->create();
    $user = User::factory()->create();

    $export = MomExport::create([
        'minutes_of_meeting_id' => $meeting->id,
        'user_id' => $user->id,
        'format' => 'pdf',
    ]);

    expect($export->format)->toBe('pdf');
    expect(MomExport::count())->toBe(1);
});
