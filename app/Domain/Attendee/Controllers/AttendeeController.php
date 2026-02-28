<?php

declare(strict_types=1);

namespace App\Domain\Attendee\Controllers;

use App\Domain\Attendee\Models\AttendeeGroup;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Attendee\Requests\AddAttendeeRequest;
use App\Domain\Attendee\Requests\BulkInviteRequest;
use App\Domain\Attendee\Services\AttendeeService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\RsvpStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AttendeeController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private AttendeeService $attendeeService,
    ) {}

    public function index(MinutesOfMeeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $attendees = $meeting->attendees()->with('user')->get();
        $groups = AttendeeGroup::query()->get();

        return view('meetings.tabs.attendees', compact('meeting', 'attendees', 'groups'));
    }

    public function store(AddAttendeeRequest $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $this->attendeeService->addAttendee($meeting, $request->validated());

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Attendee added successfully.');
    }

    public function update(Request $request, MinutesOfMeeting $meeting, MomAttendee $attendee): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $attendee->update($request->only(['name', 'email', 'role', 'department']));

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Attendee updated successfully.');
    }

    public function destroy(MinutesOfMeeting $meeting, MomAttendee $attendee): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $this->attendeeService->removeAttendee($attendee);

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Attendee removed successfully.');
    }

    public function bulkInvite(BulkInviteRequest $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $group = AttendeeGroup::query()->findOrFail($request->validated('group_id'));
        $created = $this->attendeeService->bulkInviteFromGroup($meeting, $group);

        return redirect()->route('meetings.show', $meeting)
            ->with('success', $created->count().' attendee(s) invited from group.');
    }

    public function updateRsvp(Request $request, MinutesOfMeeting $meeting, MomAttendee $attendee): RedirectResponse
    {
        $this->authorize('view', $meeting);

        $request->validate([
            'rsvp_status' => ['required', 'string'],
        ]);

        $this->attendeeService->updateRsvp($attendee, RsvpStatus::from($request->input('rsvp_status')));

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'RSVP updated successfully.');
    }

    public function markPresence(Request $request, MinutesOfMeeting $meeting, MomAttendee $attendee): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $request->validate([
            'is_present' => ['required', 'boolean'],
        ]);

        $this->attendeeService->markPresent($attendee, (bool) $request->input('is_present'));

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Attendance updated successfully.');
    }
}
