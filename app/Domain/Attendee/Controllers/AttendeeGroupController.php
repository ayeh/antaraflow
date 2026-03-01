<?php

declare(strict_types=1);

namespace App\Domain\Attendee\Controllers;

use App\Domain\Attendee\Models\AttendeeGroup;
use App\Domain\Attendee\Requests\CreateAttendeeGroupRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AttendeeGroupController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', AttendeeGroup::class);

        $groups = AttendeeGroup::query()->latest()->get();

        return view('attendee-groups.index', compact('groups'));
    }

    public function create(): View
    {
        $this->authorize('create', AttendeeGroup::class);

        return view('attendee-groups.create');
    }

    public function store(CreateAttendeeGroupRequest $request): RedirectResponse
    {
        $this->authorize('create', AttendeeGroup::class);

        $data = $request->validated();
        $data['organization_id'] = $request->user()->current_organization_id;

        AttendeeGroup::query()->create($data);

        return redirect()->route('attendee-groups.index')->with('success', 'Group created.');
    }

    public function edit(AttendeeGroup $attendeeGroup): View
    {
        $this->authorize('update', $attendeeGroup);

        return view('attendee-groups.edit', compact('attendeeGroup'));
    }

    public function update(CreateAttendeeGroupRequest $request, AttendeeGroup $attendeeGroup): RedirectResponse
    {
        $this->authorize('update', $attendeeGroup);

        $attendeeGroup->update($request->validated());

        return redirect()->route('attendee-groups.index')->with('success', 'Group updated.');
    }

    public function destroy(AttendeeGroup $attendeeGroup): RedirectResponse
    {
        $this->authorize('delete', $attendeeGroup);

        $attendeeGroup->delete();

        return redirect()->route('attendee-groups.index')->with('success', 'Group deleted.');
    }
}
