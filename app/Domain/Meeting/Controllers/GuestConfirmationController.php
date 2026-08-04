<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MomCirculationRecipient;
use Illuminate\Http\RedirectResponse;
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

    /** Record the guest's confirmation — POST /mom/confirm/{token} */
    public function store(Request $request, string $token): RedirectResponse
    {
        $recipient = MomCirculationRecipient::withoutGlobalScopes()
            ->where('token', $token)
            ->firstOrFail();

        $circulation = $recipient->circulation()->withoutGlobalScopes()->firstOrFail();

        if ($circulation->deadline_at->isPast()) {
            return redirect()->route('mom.confirm', $token)
                ->with('error', 'Tempoh pengesahan telah tamat.');
        }

        $recipient->update([
            'response' => 'confirmed',
            'responded_at' => now(),
            'responded_ip' => $request->ip(),
            'responded_user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('mom.confirm', $token)
            ->with('success', 'Minit telah disahkan. Terima kasih.');
    }

    /** Withdraw the guest's confirmation — DELETE /mom/confirm/{token} */
    public function destroy(Request $request, string $token): RedirectResponse
    {
        $recipient = MomCirculationRecipient::withoutGlobalScopes()
            ->where('token', $token)
            ->firstOrFail();

        $circulation = $recipient->circulation()->withoutGlobalScopes()->firstOrFail();

        if ($circulation->deadline_at->isPast()) {
            return redirect()->route('mom.confirm', $token)
                ->with('error', 'Tempoh pengesahan telah tamat.');
        }

        $recipient->update([
            'response' => null,
            'responded_at' => null,
            'responded_ip' => null,
            'responded_user_agent' => null,
        ]);

        return redirect()->route('mom.confirm', $token)
            ->with('info', 'Pengesahan telah ditarik balik.');
    }
}
