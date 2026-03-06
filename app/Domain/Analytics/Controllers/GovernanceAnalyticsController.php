<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Controllers;

use App\Domain\Analytics\Services\GovernanceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GovernanceAnalyticsController extends Controller
{
    public function __construct(
        private readonly GovernanceAnalyticsService $governanceService,
    ) {}

    public function index(): View
    {
        return view('analytics.governance');
    }

    public function data(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $orgId = (int) auth()->user()->current_organization_id;
        $startDate = $request->date('start_date') ?? now()->subMonths(6)->startOfMonth();
        $endDate = $request->date('end_date') ?? now()->endOfMonth();

        return response()->json($this->governanceService->getAllMetrics($orgId, $startDate, $endDate));
    }

    public function export(Request $request): StreamedResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $orgId = (int) auth()->user()->current_organization_id;
        $startDate = $request->date('start_date') ?? now()->subMonths(6)->startOfMonth();
        $endDate = $request->date('end_date') ?? now()->endOfMonth();

        $metrics = $this->governanceService->getAllMetrics($orgId, $startDate, $endDate);

        $filename = 'governance-analytics-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($metrics) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Governance Analytics Report']);
            fputcsv($handle, ['Generated at', now()->toDateTimeString()]);
            fputcsv($handle, []);

            fputcsv($handle, ['Meeting Cost Estimate']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Total Cost', $metrics['cost_estimate']['total_cost']]);
            fputcsv($handle, ['Avg Cost Per Meeting', $metrics['cost_estimate']['avg_cost_per_meeting']]);
            fputcsv($handle, ['Meeting Count', $metrics['cost_estimate']['meeting_count']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Attendance Rate Trends']);
            fputcsv($handle, ['Month', 'Present', 'Total', 'Rate (%)']);
            foreach ($metrics['attendance_trends'] as $trend) {
                fputcsv($handle, [$trend['month'], $trend['present'], $trend['total'], $trend['rate']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Action Item Completion Trends']);
            fputcsv($handle, ['Month', 'Completed On Time', 'Completed Overdue', 'Still Open']);
            foreach ($metrics['action_item_trends'] as $trend) {
                fputcsv($handle, [$trend['month'], $trend['completed_on_time'], $trend['completed_overdue'], $trend['still_open']]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Meeting Type Distribution']);
            fputcsv($handle, ['Type', 'Count']);
            foreach ($metrics['meeting_type_distribution'] as $type => $count) {
                fputcsv($handle, [$type, $count]);
            }
            fputcsv($handle, []);

            fputcsv($handle, ['Approval Turnaround']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Avg Days', $metrics['approval_turnaround']['avg_days']]);
            fputcsv($handle, ['Min Days', $metrics['approval_turnaround']['min_days']]);
            fputcsv($handle, ['Max Days', $metrics['approval_turnaround']['max_days']]);
            fputcsv($handle, ['Count', $metrics['approval_turnaround']['count']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Compliance Score']);
            fputcsv($handle, ['Metric', 'Value (%)']);
            fputcsv($handle, ['Approved Meetings', $metrics['compliance_score']['approved_percentage']]);
            fputcsv($handle, ['Action Items Assigned', $metrics['compliance_score']['action_items_assigned_percentage']]);
            fputcsv($handle, ['On-Time Completion', $metrics['compliance_score']['on_time_completion_percentage']]);
            fputcsv($handle, ['Overall Score', $metrics['compliance_score']['overall_score']]);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
