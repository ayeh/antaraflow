<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $orgId = $user->current_organization_id;

        $recentMeetings = MinutesOfMeeting::query()
            ->where('organization_id', $orgId)
            ->with('createdBy')
            ->latest()
            ->take(5)
            ->get();

        $upcomingActions = ActionItem::query()
            ->where('organization_id', $orgId)
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', [ActionItemStatus::Completed, ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])
            ->orderBy('due_date')
            ->take(5)
            ->get();

        $totalActions = ActionItem::query()->where('organization_id', $orgId)->count();
        $completedActions = ActionItem::query()->where('organization_id', $orgId)->where('status', ActionItemStatus::Completed)->count();
        $completionRate = $totalActions > 0 ? (int) round(($completedActions / $totalActions) * 100) : 0;

        $stats = [
            'total_meetings' => MinutesOfMeeting::query()->where('organization_id', $orgId)->count(),
            'pending_actions' => ActionItem::query()->where('organization_id', $orgId)->whereIn('status', [ActionItemStatus::Open, ActionItemStatus::InProgress])->count(),
            'overdue_actions' => ActionItem::query()->where('organization_id', $orgId)->whereNotIn('status', [ActionItemStatus::Completed, ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])->where('due_date', '<', now())->count(),
            'meetings_this_week' => MinutesOfMeeting::query()->where('organization_id', $orgId)->where('created_at', '>=', now()->startOfWeek())->count(),
            'completion_rate' => $completionRate,
        ];

        $upcomingMeetings = MinutesOfMeeting::query()
            ->where('organization_id', $orgId)
            ->where('meeting_date', '>=', now()->startOfDay())
            ->orderBy('meeting_date')
            ->limit(5)
            ->get();

        return view('dashboard', compact('recentMeetings', 'upcomingActions', 'stats', 'upcomingMeetings'));
    }
}
