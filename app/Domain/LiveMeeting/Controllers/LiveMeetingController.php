<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Controllers;

use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Services\LiveMeetingService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class LiveMeetingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private LiveMeetingService $liveMeetingService,
    ) {}

    public function start(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $config = $request->validate([
            'chunk_interval' => ['sometimes', 'integer', 'min:10', 'max:120'],
            'extraction_interval' => ['sometimes', 'integer', 'min:60', 'max:600'],
        ]);

        try {
            $session = $this->liveMeetingService->startSession($meeting, $request->user(), $config);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return response()->json(['session' => $session], 201);
    }

    public function show(Request $request, MinutesOfMeeting $meeting, LiveMeetingSession $session): View
    {
        $this->authorize('view', $meeting);
        abort_if($session->minutes_of_meeting_id !== $meeting->id, 404);

        $state = $this->liveMeetingService->getSessionState($session);

        return view('meetings.live-dashboard', [
            'meeting' => $meeting,
            'session' => $session,
            'state' => $state,
        ]);
    }

    public function chunk(Request $request, MinutesOfMeeting $meeting, LiveMeetingSession $session): JsonResponse
    {
        $this->authorize('update', $meeting);
        abort_if($session->minutes_of_meeting_id !== $meeting->id, 404);
        abort_if(! $session->isActive(), 409, 'Session is not active.');

        $request->validate([
            'audio' => ['required', 'file', 'mimetypes:audio/*'],
            'chunk_number' => ['required', 'integer', 'min:0'],
            'start_time' => ['required', 'numeric', 'min:0'],
            'end_time' => ['required', 'numeric', 'gt:start_time'],
        ]);

        $chunk = $this->liveMeetingService->processChunk(
            $session,
            $request->file('audio'),
            (int) $request->input('chunk_number'),
            (float) $request->input('start_time'),
            (float) $request->input('end_time'),
        );

        return response()->json(['chunk' => $chunk], 201);
    }

    public function end(Request $request, MinutesOfMeeting $meeting, LiveMeetingSession $session): JsonResponse
    {
        $this->authorize('update', $meeting);
        abort_if($session->minutes_of_meeting_id !== $meeting->id, 404);
        abort_if(! $session->isActive(), 409, 'Session is not active.');

        $this->liveMeetingService->endSession($session);

        return response()->json(['message' => 'Session ended successfully.']);
    }

    public function state(Request $request, MinutesOfMeeting $meeting, LiveMeetingSession $session): JsonResponse
    {
        $this->authorize('view', $meeting);
        abort_if($session->minutes_of_meeting_id !== $meeting->id, 404);

        $state = $this->liveMeetingService->getSessionState($session);

        return response()->json($state);
    }
}
