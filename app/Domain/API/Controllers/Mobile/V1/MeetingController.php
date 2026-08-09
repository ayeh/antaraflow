<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Requests\Mobile\StoreMeetingRequest;
use App\Domain\API\Requests\Mobile\UpdateMeetingRequest;
use App\Domain\API\Resources\Mobile\MeetingDetailResource;
use App\Domain\API\Resources\Mobile\MeetingListResource;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingController extends MobileController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MinutesOfMeeting::class);

        $meetings = $this->filtered($request)
            ->withCount([
                'attendees',
                'transcriptions',
                'liveSessions as active_live_sessions_count' => fn (Builder $query) => $query
                    ->where('status', LiveSessionStatus::Active),
            ])
            ->with(['tags', 'actionItems:id,minutes_of_meeting_id,status'])
            ->orderByDesc('meeting_date')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return $this->paginated($meetings, MeetingListResource::collection($meetings->items()));
    }

    public function show(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $meeting->load([
            'createdBy:id,name',
            'series:id,name',
            'attendees',
            'actionItems.assignedTo:id,name,avatar_path',
            'extractions',
            'resolutions.votes',
            'resolutions.mover:id,name',
            'resolutions.seconder:id,name',
            'documents',
            'voiceNotes.createdBy:id,name',
            'transcriptions',
            'tags',
            'liveSessions',
        ]);

        return response()->json(new MeetingDetailResource($meeting));
    }

    public function store(StoreMeetingRequest $request): JsonResponse
    {
        $this->authorize('create', MinutesOfMeeting::class);

        $data = $request->validated();
        $attendeeIds = $data['attendee_ids'] ?? [];
        unset($data['attendee_ids'], $data['client_id']);

        $data['status'] = MeetingStatus::Draft;
        $data['created_by'] = $this->user($request)->id;

        $meeting = MinutesOfMeeting::createForOrganization($this->organizationId($request), $data);

        if ($attendeeIds !== []) {
            $this->attachAttendees($meeting, $attendeeIds);
        }

        $meeting->load(['attendees', 'createdBy:id,name']);

        return response()->json(new MeetingDetailResource($meeting), 201);
    }

    public function update(UpdateMeetingRequest $request, MinutesOfMeeting $meeting): JsonResponse
    {
        if ($meeting->status === MeetingStatus::Approved) {
            return $this->failure(
                __('Cannot update an approved meeting.'),
                'MEETING_APPROVED_IMMUTABLE',
                422,
            );
        }

        $this->authorize('update', $meeting);

        $meeting->update($request->validated());

        return response()->json(new MeetingDetailResource($meeting->fresh()));
    }

    public function destroy(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('delete', $meeting);

        if (in_array($meeting->status, [MeetingStatus::Finalized, MeetingStatus::Approved], true)) {
            return $this->failure(
                __('Cannot delete a finalized or approved meeting.'),
                'MEETING_IMMUTABLE',
                422,
            );
        }

        $meeting->delete();

        return response()->json(null, 204);
    }

    public function finalize(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('finalize', $meeting);

        if ($meeting->status === MeetingStatus::Approved) {
            return $this->failure(
                __('This meeting has already been approved.'),
                'MEETING_APPROVED_IMMUTABLE',
                422,
            );
        }

        $meeting->update(['status' => MeetingStatus::Finalized]);

        return response()->json(new MeetingDetailResource($meeting->fresh()));
    }

    public function approve(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('approve', $meeting);

        if ($meeting->status === MeetingStatus::Draft) {
            return $this->failure(
                __('Finalize the minutes before approving them.'),
                'MEETING_NOT_FINALIZED',
                422,
            );
        }

        $meeting->update(['status' => MeetingStatus::Approved]);

        return response()->json(new MeetingDetailResource($meeting->fresh()));
    }

    /**
     * Trimmed payload for the calendar screen — a month view asks for far more
     * rows than a list does, and needs almost none of the detail.
     */
    public function calendar(Request $request): JsonResponse
    {
        $this->authorize('viewAny', MinutesOfMeeting::class);

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $meetings = MinutesOfMeeting::query()
            ->whereBetween('meeting_date', [$validated['from'], $validated['to']])
            ->orderBy('meeting_date')
            ->get(['id', 'title', 'meeting_date', 'duration_minutes', 'status', 'meeting_type', 'location']);

        return response()->json([
            'data' => $meetings->map(fn (MinutesOfMeeting $meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'meeting_date' => $meeting->meeting_date?->toIso8601String(),
                'duration_minutes' => $meeting->duration_minutes,
                'status' => $meeting->status->value,
                'meeting_type' => $meeting->meeting_type?->value,
                'location' => $meeting->location,
            ]),
        ]);
    }

    /** @return Builder<MinutesOfMeeting> */
    private function filtered(Request $request): Builder
    {
        return MinutesOfMeeting::query()
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn (Builder $q) => $q->where('meeting_type', $request->string('type')))
            ->when($request->filled('from'), fn (Builder $q) => $q->where('meeting_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->where('meeting_date', '<=', $request->date('to')))
            ->when($request->filled('project_id'), fn (Builder $q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('since'), fn (Builder $q) => $q->where('updated_at', '>', $request->date('since')))
            ->when($request->filled('tag_id'), fn (Builder $q) => $q->whereHas(
                'tags',
                fn (Builder $tags) => $tags->whereKey($request->integer('tag_id')),
            ))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $term = '%'.$request->string('q').'%';

                $q->where(fn (Builder $inner) => $inner
                    ->where('title', 'like', $term)
                    ->orWhere('summary', 'like', $term)
                    ->orWhere('mom_number', 'like', $term));
            });
    }

    /** @param  array<int, int>  $userIds */
    private function attachAttendees(MinutesOfMeeting $meeting, array $userIds): void
    {
        $users = \App\Models\User::query()
            ->whereKey($userIds)
            ->whereHas('organizations', fn (Builder $q) => $q->whereKey($meeting->organization_id))
            ->get();

        foreach ($users as $user) {
            $meeting->attendees()->create([
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
        }
    }
}
