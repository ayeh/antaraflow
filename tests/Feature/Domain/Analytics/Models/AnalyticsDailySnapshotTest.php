<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Analytics\Models\AnalyticsDailySnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can upsert daily snapshot', function (): void {
    $org = Organization::factory()->create();
    $date = now()->toDateString();

    $snapshot = AnalyticsDailySnapshot::create([
        'organization_id' => $org->id,
        'snapshot_date' => $date,
        'total_meetings' => 5,
    ]);

    $snapshot->update(['total_meetings' => 10]);

    expect(AnalyticsDailySnapshot::count())->toBe(1);
});
