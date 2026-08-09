<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Resources\Mobile\ResolutionResource;
use App\Domain\Meeting\Models\MeetingResolution;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Services\ResolutionService;
use App\Support\Enums\ResolutionStatus;
use App\Support\Enums\VoteChoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ResolutionController extends MobileController
{
    public function __construct(
        private readonly ResolutionService $resolutionService,
    ) {}

    public function index(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $resolutions = $meeting->resolutions()
            ->with(['votes', 'mover:id,name', 'seconder:id,name', 'meeting.attendees'])
            ->orderBy('resolution_number')
            ->get();

        $viewerAttendeeId = $this->viewerAttendeeId($request, $meeting);

        return response()->json([
            'data' => $resolutions->map(
                fn (MeetingResolution $resolution) => new ResolutionResource($resolution, $viewerAttendeeId)
            ),
        ]);
    }

    public function store(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('create', MeetingResolution::class);
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'mover_id' => ['sometimes', 'nullable', 'integer'],
            'seconder_id' => ['sometimes', 'nullable', 'integer'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);
        unset($validated['client_id']);

        $resolution = $this->resolutionService->create($meeting, $validated);
        $resolution->load(['votes', 'mover:id,name', 'seconder:id,name', 'meeting.attendees']);

        return response()->json(new ResolutionResource($resolution), 201);
    }

    public function update(Request $request, MeetingResolution $resolution): JsonResponse
    {
        $this->authorize('update', $resolution);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::enum(ResolutionStatus::class)],
            'mover_id' => ['sometimes', 'nullable', 'integer'],
            'seconder_id' => ['sometimes', 'nullable', 'integer'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);
        unset($validated['client_id']);

        $updated = $this->resolutionService->update($resolution, $validated);
        $updated->load(['votes', 'mover:id,name', 'seconder:id,name', 'meeting.attendees']);

        return response()->json(new ResolutionResource($updated));
    }

    public function destroy(Request $request, MeetingResolution $resolution): JsonResponse
    {
        $this->authorize('delete', $resolution);

        $this->resolutionService->delete($resolution);

        return response()->json(null, 204);
    }

    /**
     * Cast or change a vote.
     *
     * Deliberately server-authoritative and not part of the offline queue: a
     * vote is a governance record, so it is either accepted against the live
     * state of the resolution or refused outright.
     */
    public function vote(Request $request, MeetingResolution $resolution): JsonResponse
    {
        $this->authorize('vote', $resolution);

        $validated = $request->validate([
            'vote' => ['required', Rule::enum(VoteChoice::class)],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);

        if ($resolution->status !== ResolutionStatus::Proposed) {
            return $this->failure(
                __('Voting on this resolution has closed.'),
                'VOTING_CLOSED',
                409,
            );
        }

        $meeting = $resolution->meeting;
        $attendeeId = $this->viewerAttendeeId($request, $meeting);

        if ($attendeeId === null) {
            return $this->failure(
                __('You are not listed as an attendee of this meeting, so you cannot vote.'),
                'NOT_AN_ATTENDEE',
                403,
            );
        }

        $this->resolutionService->castVote($resolution, $attendeeId, VoteChoice::from($validated['vote']));

        $resolution->load(['votes', 'mover:id,name', 'seconder:id,name', 'meeting.attendees']);

        return response()->json(new ResolutionResource($resolution, $attendeeId));
    }

    /**
     * Close voting and record the outcome the tally produces.
     */
    public function close(Request $request, MeetingResolution $resolution): JsonResponse
    {
        $this->authorize('update', $resolution);

        if ($resolution->status !== ResolutionStatus::Proposed) {
            return $this->failure(
                __('Voting on this resolution has closed.'),
                'VOTING_CLOSED',
                409,
            );
        }

        $outcome = $this->resolutionService->calculateResult($resolution);
        $resolution->update(['status' => $outcome]);

        $resolution->load(['votes', 'mover:id,name', 'seconder:id,name', 'meeting.attendees']);

        return response()->json(new ResolutionResource($resolution));
    }

    private function viewerAttendeeId(Request $request, ?MinutesOfMeeting $meeting): ?int
    {
        if ($meeting === null) {
            return null;
        }

        $user = $this->user($request);

        return $meeting->attendees()
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('email', $user->email))
            ->value('id');
    }
}
