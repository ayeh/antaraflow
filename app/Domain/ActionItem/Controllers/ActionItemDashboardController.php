<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Controllers;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Services\ActionItemService;
use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ActionItemDashboardController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ActionItemService $actionItemService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ActionItem::class);

        $user = $request->user();

        $selectedStatuses = array_values(array_filter(
            array_map(
                fn (string $s) => ActionItemStatus::tryFrom($s),
                (array) $request->query('status', [])
            )
        ));

        $selectedPriorities = array_values(array_filter(
            array_map(
                fn (string $p) => ActionItemPriority::tryFrom($p),
                (array) $request->query('priority', [])
            )
        ));

        $assigneeFilter = $request->query('assignee');
        $assigneeUserId = $assigneeFilter === 'me' ? $user->id : null;

        $actionItems = $this->actionItemService->getDashboard(
            $user->current_organization_id,
            $assigneeUserId,
            $selectedStatuses,
            $selectedPriorities,
        );

        $currentView = in_array($request->query('view'), ['table', 'kanban']) ? $request->query('view') : 'table';

        return view('action-items.dashboard', compact(
            'actionItems',
            'selectedStatuses',
            'selectedPriorities',
            'assigneeFilter',
            'currentView',
        ));
    }
}
