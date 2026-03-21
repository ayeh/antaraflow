<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Analytics\Models\AnalyticsDailySnapshot;
use App\Domain\API\Controllers\ApiController;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsApiController extends ApiController
{
    public function summary(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);

        $snapshot = AnalyticsDailySnapshot::query()
            ->where('organization_id', $orgId)
            ->where('snapshot_date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('
                SUM(total_meetings) as total_meetings,
                SUM(total_action_items) as total_action_items,
                SUM(completed_action_items) as completed_action_items,
                SUM(overdue_action_items) as overdue_action_items,
                SUM(ai_usage_count) as ai_usage_count
            ')
            ->first();

        if (! $snapshot || ! $snapshot->total_meetings) {
            $summary = [
                'total_meetings' => MinutesOfMeeting::query()->where('organization_id', $orgId)->count(),
                'total_action_items' => ActionItem::query()->where('organization_id', $orgId)->count(),
                'completed_action_items' => ActionItem::query()
                    ->where('organization_id', $orgId)
                    ->where('status', 'completed')
                    ->count(),
                'overdue_action_items' => ActionItem::query()
                    ->where('organization_id', $orgId)
                    ->where('status', '!=', 'completed')
                    ->where('due_date', '<', now()->toDateString())
                    ->count(),
                'ai_usage_count' => 0,
            ];
        } else {
            $summary = [
                'total_meetings' => (int) $snapshot->total_meetings,
                'total_action_items' => (int) $snapshot->total_action_items,
                'completed_action_items' => (int) $snapshot->completed_action_items,
                'overdue_action_items' => (int) $snapshot->overdue_action_items,
                'ai_usage_count' => (int) $snapshot->ai_usage_count,
            ];
        }

        return response()->json(['data' => $summary]);
    }
}
