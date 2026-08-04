<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class MomVerificationController extends Controller
{
    public function __invoke(int $meetingId): View|Response
    {
        $meeting = MinutesOfMeeting::withoutGlobalScopes()
            ->findOrFail($meetingId);

        $circulation = MomCirculation::withoutGlobalScopes()
            ->where('minutes_of_meeting_id', $meeting->id)
            ->whereIn('status', ['closed_approved', 'closed_amended'])
            ->with('recipients')
            ->latest()
            ->first();

        $confirmedCount = $circulation?->recipients->filter(
            fn ($r) => $r->response === 'confirmed' || $r->deemed_confirmed_at !== null
        )->count() ?? 0;

        $totalCount = $circulation?->recipients->count() ?? 0;

        return view('meetings.mom-verify', compact('meeting', 'circulation', 'confirmedCount', 'totalCount'));
    }
}
