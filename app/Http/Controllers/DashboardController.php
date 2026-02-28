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

        $stats = [
            'total_meetings' => MinutesOfMeeting::query()->where('organization_id', $orgId)->count(),
            'pending_actions' => ActionItem::query()->where('organization_id', $orgId)->whereIn('status', [ActionItemStatus::Open, ActionItemStatus::InProgress])->count(),
            'overdue_actions' => ActionItem::query()->where('organization_id', $orgId)->whereNotIn('status', [ActionItemStatus::Completed, ActionItemStatus::Cancelled, ActionItemStatus::CarriedForward])->where('due_date', '<', now())->count(),
        ];

        return view('dashboard', compact('recentMeetings', 'upcomingActions', 'stats'));
    }
}
