<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Controllers;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Requests\BulkActionItemRequest;
use App\Domain\ActionItem\Services\ActionItemService;
use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class ActionItemBulkController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ActionItemService $actionItemService,
    ) {}

    public function __invoke(BulkActionItemRequest $request): JsonResponse
    {
        $user = $request->user();
        $action = $request->validated('action');

        $items = ActionItem::query()
            ->whereIn('id', $request->validated('ids'))
            ->where('organization_id', $user->current_organization_id)
            ->get();

        if ($items->isEmpty()) {
            return response()->json(['updated' => 0]);
        }

        $policyAbility = $action === 'delete' ? 'delete' : 'update';

        $updated = 0;

        foreach ($items as $item) {
            $this->authorize($policyAbility, $item);

            if ($action === 'status') {
                $this->actionItemService->changeStatus(
                    $item,
                    ActionItemStatus::from($request->validated('value')),
                    $user,
                );
            } elseif ($action === 'priority') {
                $this->actionItemService->update(
                    $item,
                    ['priority' => ActionItemPriority::from($request->validated('value'))],
                    $user,
                );
            } elseif ($action === 'delete') {
                $item->delete();
            }

            $updated++;
        }

        return response()->json(['updated' => $updated]);
    }
}
