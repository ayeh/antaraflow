<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Collaboration\Services\CommentService;
use App\Domain\Collaboration\Services\ShareService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Requests\CreateMeetingRequest;
use App\Domain\Meeting\Requests\UpdateMeetingRequest;
use App\Domain\Meeting\Services\MeetingSearchService;
use App\Domain\Meeting\Services\MeetingService;
use App\Domain\Project\Models\Project;
use App\Models\User;
use App\Support\Enums\ActionItemStatus;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class MeetingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private MeetingService $meetingService,
        private MeetingSearchService $searchService,
        private ShareService $shareService,
        private CommentService $commentService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MinutesOfMeeting::class);

        $stats = [
            'total' => MinutesOfMeeting::count(),
            'draft' => MinutesOfMeeting::where('status', MeetingStatus::Draft)->count(),
            'finalized' => MinutesOfMeeting::where('status', MeetingStatus::Finalized)->count(),
            'approved' => MinutesOfMeeting::where('status', MeetingStatus::Approved)->count(),
        ];

        $projects = Project::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        $meetings = $this->searchService->search($request->all());

        return view('meetings.index', compact('meetings', 'stats', 'projects'));
    }

    public function create(): View
    {
        $this->authorize('create', MinutesOfMeeting::class);

        $projects = Project::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']);

        return view('meetings.create', compact('projects'));
    }

    public function store(CreateMeetingRequest $request): RedirectResponse
    {
        $this->authorize('create', MinutesOfMeeting::class);

        $meeting = $this->meetingService->create($request->validated(), $request->user());

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Meeting created successfully.');
    }

    public function show(MinutesOfMeeting $meeting, Request $request): View
    {
        $this->authorize('view', $meeting);

        $meeting->load([
            'createdBy', 'project', 'tags',
            'attendees.user', 'actionItems.assignedTo',
            'inputs', 'transcriptions', 'manualNotes', 'documents',
            'extractions', 'aiConversations',
        ]);

        $isEditable = in_array($meeting->status, [MeetingStatus::Draft, MeetingStatus::InProgress]);

        $user = $request->user()->loadMissing('currentOrganization');
        $orgMembers = User::where('current_organization_id', $user->current_organization_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $attendeeStats = [
            'total' => $meeting->attendees->count(),
            'present' => $meeting->attendees->where('is_present', true)->count(),
            'absent' => $meeting->attendees->where('is_present', false)->count(),
            'confirmed' => $meeting->attendees->where('rsvp_status', 'accepted')->count(),
        ];

        $actionItemStats = [
            'total' => $meeting->actionItems->count(),
            'completed' => $meeting->actionItems->where('status', ActionItemStatus::Completed)->count(),
            'in_progress' => $meeting->actionItems->where('status', ActionItemStatus::InProgress)->count(),
            'overdue' => $meeting->actionItems->filter(fn ($ai) => $ai->due_date && $ai->due_date->isPast() &&
                ! in_array($ai->status, [ActionItemStatus::Completed, ActionItemStatus::Cancelled])
            )->count(),
        ];

        $comments = $this->commentService->getComments($meeting);
        $shares = $this->shareService->getSharesForMeeting($meeting);

        return view('meetings.show', compact(
            'meeting', 'isEditable', 'orgMembers',
            'attendeeStats', 'actionItemStats',
            'comments', 'shares',
        ));
    }

    public function edit(MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        return redirect()->route('meetings.show', ['meeting' => $meeting, 'step' => 1]);
    }

    public function update(UpdateMeetingRequest $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $this->meetingService->update($meeting, $request->validated());

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Meeting updated successfully.');
    }

    public function destroy(MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->authorize('delete', $meeting);

        $this->meetingService->delete($meeting);

        return redirect()->route('meetings.index')
            ->with('success', 'Meeting deleted successfully.');
    }

    public function finalize(MinutesOfMeeting $meeting, Request $request): RedirectResponse
    {
        $this->authorize('finalize', $meeting);

        try {
            $this->meetingService->finalize($meeting, $request->user());
        } catch (\DomainException $e) {
            return redirect()->route('meetings.show', $meeting)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Meeting finalized successfully.');
    }

    public function approve(MinutesOfMeeting $meeting, Request $request): RedirectResponse
    {
        $this->authorize('approve', $meeting);

        try {
            $this->meetingService->approve($meeting, $request->user());
        } catch (\DomainException $e) {
            return redirect()->route('meetings.show', $meeting)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Meeting approved successfully.');
    }

    public function revert(MinutesOfMeeting $meeting, Request $request): RedirectResponse
    {
        $this->authorize('update', $meeting);

        try {
            $this->meetingService->revertToDraft($meeting, $request->user());
        } catch (\DomainException $e) {
            return redirect()->route('meetings.show', $meeting)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Meeting reverted to draft.');
    }
}
