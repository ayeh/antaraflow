<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Jobs;

use App\Domain\Account\Models\Organization;
use App\Domain\Analytics\Models\AnalyticsDailySnapshot;
use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDailyAnalyticsSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $date = today()->subDay();
        $orgIds = Organization::query()->pluck('id');

        foreach ($orgIds as $orgId) {
            $start = $date->copy()->startOfDay();
            $end = $date->copy()->endOfDay();

            $totalMeetings = MinutesOfMeeting::query()
                ->where('organization_id', $orgId)
                ->whereDate('meeting_date', $date)
                ->count();

            $aiUsageCount = AnalyticsEvent::query()
                ->where('organization_id', $orgId)
                ->where('event_type', 'like', 'ai.%')
                ->whereBetween('occurred_at', [$start, $end])
                ->count();

            AnalyticsDailySnapshot::updateOrCreate(
                ['organization_id' => $orgId, 'snapshot_date' => $date->toDateString()],
                [
                    'total_meetings' => $totalMeetings,
                    'ai_usage_count' => $aiUsageCount,
                ]
            );
        }
    }
}
