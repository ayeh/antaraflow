<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Analytics\Jobs\GenerateDailyAnalyticsSnapshotJob;
use App\Domain\Analytics\Models\AnalyticsDailySnapshot;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates daily snapshots for each organization', function (): void {
    $org = Organization::factory()->create();
    MinutesOfMeeting::factory()->for($org)->count(3)->create([
        'meeting_date' => now()->subDay()->toDateString(),
    ]);

    (new GenerateDailyAnalyticsSnapshotJob)->handle();

    expect(AnalyticsDailySnapshot::where('organization_id', $org->id)->count())->toBe(1);
    expect(AnalyticsDailySnapshot::where('organization_id', $org->id)->value('total_meetings'))->toBe(3);
});
