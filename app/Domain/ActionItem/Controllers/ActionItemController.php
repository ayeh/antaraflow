<?php

declare(strict_types=1);

namespace App\Domain\ActionItem\Controllers;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Requests\CreateActionItemRequest;
use App\Domain\ActionItem\Requests\UpdateActionItemRequest;
use App\Domain\ActionItem\Services\ActionItemService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
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

    public function show(MinutesOfMeeting $meeting, ActionItem $actionItem): View
    {
        $this->authorize('view', $actionItem);

        $actionItem->load(['assignedTo', 'createdBy', 'histories.changedBy', 'carriedFrom', 'carriedTo']);

        return view('action-items.show', compact('meeting', 'actionItem'));
    }

    public function edit(MinutesOfMeeting $meeting, ActionItem $actionItem): View
    {
        $this->authorize('update', $actionItem);

        return view('action-items.edit', compact('meeting', 'actionItem'));
    }

    public function update(UpdateActionItemRequest $request, MinutesOfMeeting $meeting, ActionItem $actionItem): RedirectResponse
    {
        $this->authorize('update', $actionItem);

        $this->actionItemService->update($actionItem, $request->validated(), $request->user());

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
