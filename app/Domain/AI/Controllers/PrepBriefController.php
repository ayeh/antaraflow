<?php

declare(strict_types=1);

namespace App\Domain\AI\Controllers;

use App\Domain\AI\Models\MeetingPrepBrief;
use App\Domain\AI\Services\MeetingPrepBriefService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class PrepBriefController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private MeetingPrepBriefService $briefService,
    ) {}

    public function show(Request $request, MinutesOfMeeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $brief = MeetingPrepBrief::query()
            ->where('minutes_of_meeting_id', $meeting->id)
            ->where('user_id', $request->user()->id)
            ->latest('generated_at')
            ->first();

        if ($brief && ! $brief->viewed_at) {
            $brief->markAsViewed();
        }

        return view('meetings.prep-brief', [
            'meeting' => $meeting,
            'brief' => $brief,
        ]);
    }

    public function generate(Request $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->authorize('view', $meeting);

        $this->briefService->generateForUser($meeting, $request->user());

        return redirect()->back()->with('success', 'Prep brief generated successfully.');
    }

    public function markSectionRead(Request $request, MinutesOfMeeting $meeting, MeetingPrepBrief $brief): JsonResponse
    {
        $this->authorize('view', $meeting);

        $validated = $request->validate([
            'section' => ['required', 'string', 'max:50'],
        ]);

        $brief->markSectionRead($validated['section']);

        return response()->json(['status' => 'ok']);
    }
}
