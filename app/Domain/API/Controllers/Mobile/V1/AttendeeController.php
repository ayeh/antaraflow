<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Resources\Mobile\AttendeeResource;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\AttendeeRole;
use App\Support\Enums\RsvpStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendeeController extends MobileController
{
    public function index(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        return response()->json([
            'data' => AttendeeResource::collection($meeting->attendees()->orderBy('name')->get()),
        ]);
    }

    public function store(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role' => ['sometimes', Rule::enum(AttendeeRole::class)],
            'user_id' => ['sometimes', 'nullable', 'integer'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);
        unset($validated['client_id']);

        $attendee = $meeting->attendees()->create($validated);

        return response()->json(new AttendeeResource($attendee), 201);
    }

    public function updateRsvp(Request $request, MomAttendee $attendee): JsonResponse
    {
        $meeting = $this->meetingFor($attendee);
        $this->authorize('view', $meeting);

        $validated = $request->validate([
            'rsvp_status' => ['required', Rule::enum(RsvpStatus::class)],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $attendee->update(['rsvp_status' => $validated['rsvp_status']]);

        return response()->json(new AttendeeResource($attendee->fresh()));
    }

    public function updatePresence(Request $request, MomAttendee $attendee): JsonResponse
    {
        $meeting = $this->meetingFor($attendee);
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'is_present' => ['required', 'boolean'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $attendee->update(['is_present' => $validated['is_present']]);

        return response()->json(new AttendeeResource($attendee->fresh()));
    }

    public function destroy(Request $request, MomAttendee $attendee): JsonResponse
    {
        $meeting = $this->meetingFor($attendee);
        $this->authorize('update', $meeting);

        $attendee->delete();

        return response()->json(null, 204);
    }

    private function meetingFor(MomAttendee $attendee): MinutesOfMeeting
    {
        $meeting = MinutesOfMeeting::query()->find($attendee->minutes_of_meeting_id);

        // Scoped away means it belongs to another tenant; say nothing more.
        abort_if($meeting === null, 404);

        return $meeting;
    }
}
