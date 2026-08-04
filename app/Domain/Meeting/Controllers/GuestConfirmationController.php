<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MomCirculationRecipient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class GuestConfirmationController extends Controller
{
    /** Public guest confirmation view — no auth required */
    public function show(Request $request, string $token): View
    {
        // CRITICAL: withoutGlobalScopes() because OrganizationScope resolves to 1=0 for guests
        $recipient = MomCirculationRecipient::withoutGlobalScopes()
            ->where('token', $token)
            ->firstOrFail();

        // Track open — increment first to avoid race conditions
        $now = now();
        $recipient->increment('open_count');

        if ($recipient->first_opened_at === null) {
            $recipient->update(['first_opened_at' => $now]);
        }

        $recipient->update(['last_opened_at' => $now]);

        // Load the meeting via the circulation (also without scopes)
        $circulation = $recipient->circulation()->withoutGlobalScopes()->with([
            'meeting' => fn ($q) => $q->withoutGlobalScopes()->with([
                'topics' => fn ($q) => $q->orderBy('sort_order'),
                'actionItems' => fn ($q) => $q->where('client_visible', true),
                'attendees' => fn ($q) => $q->select('id', 'minutes_of_meeting_id', 'name', 'role'),
            ]),
        ])->firstOrFail();

        $meeting = $circulation->meeting;

        return view('meetings.mom-confirm', compact('recipient', 'circulation', 'meeting'));
    }
}
