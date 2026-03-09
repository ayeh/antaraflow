<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Controllers;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Requests\CreateActionItemRequest;
use App\Domain\ActionItem\Requests\UpdateActionItemRequest;
use App\Domain\ActionItem\Services\ActionItemService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ActionItemController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ActionItemService $actionItemService,
    ) {}

    public function index(MinutesOfMeeting $meeting): View
    {
        $this->authorize('viewAny', ActionItem::class);

        $actionItems = $meeting->actionItems()->with(['assignedTo', 'createdBy'])->get();

        return view('action-items.index', compact('meeting', 'actionItems'));
    }

    public function create(MinutesOfMeeting $meeting): View
    {
        $this->authorize('create', ActionItem::class);

        return view('action-items.create', compact('meeting'));
    }

    public function store(CreateActionItemRequest $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->authorize('create', ActionItem::class);

        $this->actionItemService->create($request->validated(), $meeting, $request->user());

        return redirect()->route('meetings.action-items.index', $meeting)
            ->with('success', 'Action item created successfully.');
    }

    public function show(\Illuminate\Http\Request $request, MinutesOfMeeting $meeting, ActionItem $actionItem): \Illuminate\View\View|\Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $actionItem);

        $actionItem->load(['assignedTo', 'createdBy', 'histories.changedBy', 'carriedFrom', 'carriedTo']);

        if ($request->wantsJson()) {
            $users = User::where('current_organization_id', $request->user()->current_organization_id)
                ->orderBy('name')
                ->get(['id', 'name']);

            return response()->json([
                'id' => $actionItem->id,
                'title' => $actionItem->title,
                'description' => $actionItem->description,
                'status' => $actionItem->status->value,
                'priority' => $actionItem->priority->value,
                'due_date' => $actionItem->due_date?->format('Y-m-d'),
                'assigned_to' => $actionItem->assigned_to,
                'meeting_id' => $meeting->id,
                'show_url' => route('meetings.action-items.show', [$meeting, $actionItem]),
                'update_url' => route('meetings.action-items.update', [$meeting, $actionItem]),
                'status_url' => route('meetings.action-items.status', [$meeting, $actionItem]),
                'users' => $users,
                'history' => $actionItem->histories->sortByDesc('created_at')->map(fn ($h) => [
                    'id' => $h->id,
                    'changed_by_name' => $h->changedBy?->name ?? 'Someone',
                    'field_changed' => $h->field_changed,
                    'old_value' => $h->old_value,
                    'new_value' => $h->new_value,
                    'comment' => $h->comment,
                    'has_comment' => (bool) $h->comment,
                    'created_at_human' => $h->created_at->diffForHumans(),
                    'created_at_formatted' => $h->created_at->format('M j, Y H:i'),
                    'old_label' => \App\Support\Enums\ActionItemStatus::tryFrom($h->old_value)?->label() ?? $h->old_value,
                    'new_label' => \App\Support\Enums\ActionItemStatus::tryFrom($h->new_value)?->label() ?? $h->new_value,
                    'new_color_class' => \App\Support\Enums\ActionItemStatus::tryFrom($h->new_value)?->colorClass() ?? 'bg-gray-100 text-gray-600',
                    'status_changed' => $h->field_changed === 'status' && $h->old_value !== $h->new_value,
                ])->values(),
            ]);
        }

        return view('action-items.show', compact('meeting', 'actionItem'));
    }

    public function edit(MinutesOfMeeting $meeting, ActionItem $actionItem): View
    {
        $this->authorize('update', $actionItem);

        return view('action-items.edit', compact('meeting', 'actionItem'));
    }

    public function update(UpdateActionItemRequest $request, MinutesOfMeeting $meeting, ActionItem $actionItem): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $actionItem);

        $this->actionItemService->update($actionItem, $request->validated(), $request->user());

        if ($request->wantsJson()) {
            $actionItem->refresh()->load('assignedTo');

            return response()->json([
                'id' => $actionItem->id,
                'title' => $actionItem->title,
                'description' => $actionItem->description,
                'status' => $actionItem->status->value,
                'status_label' => $actionItem->status->label(),
                'status_color_class' => $actionItem->status->colorClass(),
                'priority' => $actionItem->priority->value,
                'priority_label' => $actionItem->priority->label(),
                'priority_color_class' => $actionItem->priority->colorClass(),
                'due_date' => $actionItem->due_date?->format('Y-m-d'),
                'due_date_formatted' => $actionItem->due_date?->format('M j, Y'),
                'due_date_past' => $actionItem->due_date?->isPast()
                    && $actionItem->status !== \App\Support\Enums\ActionItemStatus::Completed,
                'assigned_to' => $actionItem->assigned_to,
                'assigned_to_name' => $actionItem->assignedTo?->name,
            ]);
        }

        return redirect()->route('meetings.action-items.show', [$meeting, $actionItem])
            ->with('success', 'Action item updated successfully.');
    }

    public function destroy(MinutesOfMeeting $meeting, ActionItem $actionItem): RedirectResponse
    {
        $this->authorize('delete', $actionItem);

        $actionItem->delete();

        return redirect()->route('meetings.action-items.index', $meeting)
            ->with('success', 'Action item deleted successfully.');
    }

    public function carryForward(Request $request, MinutesOfMeeting $meeting, ActionItem $actionItem): RedirectResponse
    {
        $this->authorize('update', $actionItem);

        $request->validate([
            'new_meeting_id' => ['required', 'exists:minutes_of_meetings,id'],
        ]);

        $newMom = MinutesOfMeeting::query()->findOrFail($request->input('new_meeting_id'));

        $this->actionItemService->carryForward($actionItem, $newMom, $request->user());

        return redirect()->route('meetings.action-items.index', $meeting)
            ->with('success', 'Action item carried forward successfully.');
    }

    public function createAllTasks(MinutesOfMeeting $meeting, Request $request): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $count = $this->actionItemService->createAllTasks($meeting, $request->user());

        return redirect()->route('meetings.show', ['meeting' => $meeting, 'step' => 4])
            ->with('success', "{$count} action item(s) marked as tasks created.");
    }
}
