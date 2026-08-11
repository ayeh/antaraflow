<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Services\ActionItemService;
use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Requests\Mobile\StoreActionItemRequest;
use App\Domain\API\Requests\Mobile\UpdateActionItemRequest;
use App\Domain\API\Resources\Mobile\ActionItemResource;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActionItemController extends MobileController
{
    public function __construct(
        private readonly ActionItemService $actionItemService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ActionItem::class);

        $items = ActionItem::query()
            ->with(['assignedTo:id,name,avatar_path', 'meeting:id,title,mom_number'])
            ->when(
                $request->boolean('assigned_to_me', true),
                fn (Builder $q) => $q->where('assigned_to', $this->user($request)->id),
            )
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn (Builder $q) => $q->where('priority', $request->string('priority')))
            ->when($request->filled('meeting_id'), fn (Builder $q) => $q->where('minutes_of_meeting_id', $request->integer('meeting_id')))
            ->when($request->filled('due_before'), fn (Builder $q) => $q->where('due_date', '<=', $request->date('due_before')))
            ->when($request->filled('due_after'), fn (Builder $q) => $q->where('due_date', '>=', $request->date('due_after')))
            ->when($request->filled('since'), fn (Builder $q) => $q->where('updated_at', '>', $request->date('since')))
            ->when($request->boolean('overdue'), fn (Builder $q) => $q
                ->whereNotNull('due_date')
                ->where('due_date', '<', now())
                ->whereNotIn('status', [ActionItemStatus::Completed->value, ActionItemStatus::Cancelled->value]))
            ->when($request->filled('q'), fn (Builder $q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
            ->orderByRaw('due_date is null')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return $this->paginated($items, ActionItemResource::collection($items->items()));
    }

    public function show(Request $request, ActionItem $actionItem): JsonResponse
    {
        $this->authorize('view', $actionItem);

        $actionItem->load(['assignedTo:id,name,avatar_path', 'meeting:id,title,mom_number']);

        return response()->json(new ActionItemResource($actionItem));
    }

    public function store(StoreActionItemRequest $request): JsonResponse
    {
        $this->authorize('create', ActionItem::class);

        $data = $request->validated();
        $meeting = MinutesOfMeeting::query()->findOrFail($data['minutes_of_meeting_id']);
        $this->authorize('view', $meeting);

        unset($data['minutes_of_meeting_id'], $data['client_id']);

        $item = $this->actionItemService->create($data, $meeting, $this->user($request));
        $item->load(['assignedTo:id,name,avatar_path', 'meeting:id,title,mom_number']);

        return response()->json(new ActionItemResource($item), 201);
    }

    public function update(UpdateActionItemRequest $request, ActionItem $actionItem): JsonResponse
    {
        $this->authorize('update', $actionItem);

        $data = $request->validated();
        unset($data['client_id']);

        $item = $this->actionItemService->update($actionItem, $data, $this->user($request));
        $item->load(['assignedTo:id,name,avatar_path', 'meeting:id,title,mom_number']);

        return response()->json(new ActionItemResource($item));
    }

    /**
     * Narrow endpoint for swipe-to-complete. Kept separate from update() so a
     * queued offline status change carries nothing else that could go stale.
     */
    public function updateStatus(Request $request, ActionItem $actionItem): JsonResponse
    {
        $this->authorize('update', $actionItem);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ActionItemStatus::class)],
            'comment' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $item = $this->actionItemService->changeStatus(
            $actionItem,
            ActionItemStatus::from($validated['status']),
            $this->user($request),
            $validated['comment'] ?? null,
        );

        $item->load(['assignedTo:id,name,avatar_path', 'meeting:id,title,mom_number']);

        return response()->json(new ActionItemResource($item));
    }

    public function destroy(Request $request, ActionItem $actionItem): JsonResponse
    {
        $this->authorize('delete', $actionItem);

        $actionItem->delete();

        return response()->json(null, 204);
    }

    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['complete', 'cancel', 'reassign'])],
            'assigned_to' => ['required_if:action,reassign', 'nullable', 'integer'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $user = $this->user($request);
        $items = ActionItem::query()->whereKey($validated['ids'])->get();
        $applied = [];
        $skipped = [];

        foreach ($items as $item) {
            if (! $request->user()->can('update', $item)) {
                $skipped[] = ['id' => $item->id, 'reason' => 'FORBIDDEN'];

                continue;
            }

            match ($validated['action']) {
                'complete' => $this->actionItemService->changeStatus($item, ActionItemStatus::Completed, $user),
                'cancel' => $this->actionItemService->changeStatus($item, ActionItemStatus::Cancelled, $user),
                'reassign' => $this->actionItemService->update($item, ['assigned_to' => $validated['assigned_to']], $user),
            };

            $applied[] = $item->id;
        }

        return response()->json(['applied' => $applied, 'skipped' => $skipped]);
    }
}
