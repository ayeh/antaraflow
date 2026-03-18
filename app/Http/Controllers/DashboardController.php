<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Account\Models\AuditLog;
use App\Domain\Account\Services\AuthorizationService;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\MeetingStatus;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private AuthorizationService $authorizationService) {}

    public function index(): View
    {
        $user = auth()->user();
        $orgId = $user->current_organization_id;

        // Personalized stat card data
        $myActionsCount = ActionItem::query()
            ->where('organization_id', $orgId)
            ->where('assigned_to', $user->id)
            ->whereIn('status', [ActionItemStatus::Open, ActionItemStatus::InProgress])
            ->count();

        $myOverdueCount = ActionItem::query()
            ->where('organization_id', $orgId)
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', [ActionItemStatus::Completed, ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])
            ->where('due_date', '<', now())
            ->count();

        $meetingsThisWeek = MinutesOfMeeting::query()
            ->where('organization_id', $orgId)
            ->whereBetween('meeting_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $pendingApproval = MinutesOfMeeting::query()
            ->where('organization_id', $orgId)
            ->where('status', MeetingStatus::Finalized)
            ->count();

        $myTotal = ActionItem::query()
            ->where('organization_id', $orgId)
            ->where('assigned_to', $user->id)
            ->count();

        $myCompleted = ActionItem::query()
            ->where('organization_id', $orgId)
            ->where('assigned_to', $user->id)
            ->where('status', ActionItemStatus::Completed)
            ->count();

        $stats = [
            'my_actions' => $myActionsCount,
            'my_overdue' => $myOverdueCount,
            'meetings_this_week' => $meetingsThisWeek,
            'pending_approval' => $pendingApproval,
            'my_completion_rate' => $myTotal > 0 ? (int) round(($myCompleted / $myTotal) * 100) : 0,
        ];

        // This week's meetings for the left column
        $thisWeekMeetings = MinutesOfMeeting::query()
            ->where('organization_id', $orgId)
            ->whereBetween('meeting_date', [now()->startOfWeek(), now()->endOfWeek()])
            ->with(['project'])
            ->orderBy('meeting_date')
            ->get();

        // My upcoming action items (assigned to me, not done)
        $upcomingActions = ActionItem::query()
            ->where('organization_id', $orgId)
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', [ActionItemStatus::Completed, ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])
            ->orderBy('due_date')
            ->take(5)
            ->get();

        // Recent org-wide MOM audit activity
        $recentActivity = AuditLog::query()
            ->where('organization_id', $orgId)
            ->where('auditable_type', MinutesOfMeeting::class)
            ->whereIn('action', ['created', 'finalized', 'approved', 'reverted_to_draft'])
            ->with(['user', 'auditable'])
            ->latest()
            ->take(8)
            ->get();

        $canCreateMeeting = $this->authorizationService->hasPermission($user, $user->currentOrganization, 'create_meeting');

        return view('dashboard', compact(
            'stats',
            'myOverdueCount',
            'pendingApproval',
            'thisWeekMeetings',
            'upcomingActions',
            'recentActivity',
            'canCreateMeeting',
        ));
    }
}
