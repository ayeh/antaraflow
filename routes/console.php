<?php

use App\Domain\ActionItem\Jobs\CheckOverdueActionItemsJob;
use App\Domain\AI\Jobs\CheckStaleDecisionsJob;
use App\Domain\AI\Jobs\GeneratePrepBriefsJob;
use App\Domain\AI\Jobs\GenerateProactiveInsightsJob;
use App\Domain\Analytics\Jobs\GenerateDailyAnalyticsSnapshotJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new CheckOverdueActionItemsJob)->dailyAt('08:00');
Schedule::job(new GeneratePrepBriefsJob)->dailyAt('08:00');
Schedule::job(GenerateDailyAnalyticsSnapshotJob::class)->dailyAt('01:00');

Schedule::command('transcription:cleanup-chunks')->hourly();

Schedule::command('reports:generate-scheduled')->hourly();

Schedule::job(new CheckStaleDecisionsJob)->weeklyOn(1, '09:00');

Schedule::job(new GenerateProactiveInsightsJob)->dailyAt('07:00');
