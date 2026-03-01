<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Controllers;

use App\Domain\Analytics\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    public function index(): View
    {
        $orgId = auth()->user()->current_organization_id;
        $startDate = now()->subMonths(6)->startOfMonth();
        $endDate = now()->endOfMonth();

        $meetingStats = $this->analyticsService->getMeetingStats($orgId, $startDate, $endDate);
        $actionStats = $this->analyticsService->getActionItemStats($orgId, $startDate, $endDate);
        $participationStats = $this->analyticsService->getParticipationStats($orgId, $startDate, $endDate);
        $aiStats = $this->analyticsService->getAiUsageStats($orgId, $startDate, $endDate);

        return view('analytics.index', compact('meetingStats', 'actionStats', 'participationStats', 'aiStats'));
    }

    public function data(Request $request): JsonResponse
    {
        $orgId = auth()->user()->current_organization_id;
        $startDate = $request->date('start_date') ?? now()->subMonths(6)->startOfMonth();
        $endDate = $request->date('end_date') ?? now()->endOfMonth();

        return response()->json([
            'meetings' => $this->analyticsService->getMeetingStats($orgId, $startDate, $endDate),
            'actions' => $this->analyticsService->getActionItemStats($orgId, $startDate, $endDate),
            'participation' => $this->analyticsService->getParticipationStats($orgId, $startDate, $endDate),
            'ai' => $this->analyticsService->getAiUsageStats($orgId, $startDate, $endDate),
        ]);
    }
}
