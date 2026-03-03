<?php

declare(strict_types=1);

namespace App\Domain\Admin\Controllers;

use App\Domain\Admin\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private AnalyticsService $analyticsService,
    ) {}

    public function index(Request $request): View
    {
        $period = $request->input('period', 'daily');

        $stats = $this->analyticsService->getStatCards();
        $userGrowth = $this->analyticsService->getUserGrowth($period);
        $orgGrowth = $this->analyticsService->getOrgGrowth($period);
        $subscriptionDistribution = $this->analyticsService->getSubscriptionDistribution();
        $recentRegistrations = $this->analyticsService->getRecentRegistrations();
        $topOrganizations = $this->analyticsService->getTopOrganizations();
        $activityHeatmap = $this->analyticsService->getActivityHeatmap();

        return view('admin.dashboard', compact(
            'stats',
            'userGrowth',
            'orgGrowth',
            'subscriptionDistribution',
            'recentRegistrations',
            'topOrganizations',
            'activityHeatmap',
            'period',
        ));
    }
}
