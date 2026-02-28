<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Controllers;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Services\ActionItemService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ActionItemDashboardController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ActionItemService $actionItemService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ActionItem::class);

        $user = $request->user();
        $actionItems = $this->actionItemService->getDashboard(
            $user->current_organization_id,
            $request->query('assignee') === 'me' ? $user->id : null,
        );

        return view('action-items.dashboard', compact('actionItems'));
    }
}
