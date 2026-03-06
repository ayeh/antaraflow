<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MeetingResolution;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Requests\CastVoteRequest;
use App\Domain\Meeting\Services\ResolutionService;
use App\Support\Enums\VoteChoice;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class VoteController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ResolutionService $resolutionService,
    ) {}

    public function store(CastVoteRequest $request, MinutesOfMeeting $meeting, MeetingResolution $resolution): RedirectResponse
    {
        $this->authorize('vote', $resolution);

        $this->resolutionService->castVote(
            $resolution,
            (int) $request->validated('attendee_id'),
            VoteChoice::from($request->validated('vote')),
        );

        return redirect()->route('meetings.show', ['meeting' => $meeting, 'step' => 5])
            ->with('success', 'Vote recorded successfully.');
    }
}
