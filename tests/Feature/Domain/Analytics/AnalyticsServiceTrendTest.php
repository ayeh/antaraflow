<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Analytics\Models\AnalyticsDailySnapshot;
use App\Domain\Analytics\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns trend data from snapshots', function (): void {
    $org = Organization::factory()->create();
    AnalyticsDailySnapshot::create([
        'organization_id' => $org->id,
        'snapshot_date' => now()->subDays(5)->toDateString(),
        'total_meetings' => 3,
    ]);

    $service = app(AnalyticsService::class);
    $trend = $service->getTrendData($org->id, 30);

    expect($trend)->toHaveCount(1);
    expect($trend[0]['total_meetings'])->toBe(3);
});
