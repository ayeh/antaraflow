<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Export\Models\MomExport;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user->id, ['role' => 'member']);
    $this->meeting = MinutesOfMeeting::factory()->for($this->org)->create();
});

it('saves export record when PDF is downloaded', function (): void {
    $this->actingAs($this->user)
        ->get(route('meetings.export.pdf', $this->meeting));

    expect(MomExport::where('minutes_of_meeting_id', $this->meeting->id)
        ->where('format', 'pdf')
        ->exists()
    )->toBeTrue();
});
