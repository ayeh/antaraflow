<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MomCirculation;
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

        if ($locked = $this->lockedReason($circulation)) {
            return redirect()->route('mom.confirm', $token)
                ->with('error', $locked);
        }

        $recipient->update([
            'response' => 'confirmed',
            'responded_at' => now(),
            'responded_ip' => $request->ip(),
            'responded_user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('mom.confirm', $token)
            ->with('success', __('mom.confirm_success'));
    }

    /** Store a guest remark anchored to a topic or action item — POST /mom/confirm/{token}/remark */
    public function remark(Request $request, string $token): RedirectResponse
    {
        $recipient = MomCirculationRecipient::withoutGlobalScopes()
            ->where('token', $token)
            ->firstOrFail();

        $circulation = $recipient->circulation()->withoutGlobalScopes()->firstOrFail();

        if ($locked = $this->lockedReason($circulation)) {
            return redirect()->route('mom.confirm', $token)
                ->with('error', $locked);
        }

        $validated = $request->validate([
            'commentable_type' => ['required', 'in:topic,action_item'],
            'commentable_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $commentableClass = match ($validated['commentable_type']) {
            'topic' => \App\Domain\AI\Models\MomTopic::class,
            'action_item' => \App\Domain\ActionItem\Models\ActionItem::class,
        };

        // Security: verify the commentable belongs to this meeting's circulation.
        $commentable = $commentableClass::withoutGlobalScopes()
            ->where('id', $validated['commentable_id'])
            ->where('minutes_of_meeting_id', $circulation->minutes_of_meeting_id)
            ->firstOrFail();

        \App\Domain\Collaboration\Models\Comment::createForOrganization($circulation->organization_id, [
            'user_id' => null,
            'mom_circulation_recipient_id' => $recipient->id,
            'commentable_type' => $commentableClass,
            'commentable_id' => $commentable->id,
            'body' => $validated['body'],
            'client_visible' => true,
        ]);

        if ($recipient->response !== 'confirmed') {
            $recipient->update(['response' => 'amendment_requested']);
        }

        return redirect()->route('mom.confirm', $token)
            ->with('success', __('mom.remark_success'));
    }

    /** Explicitly declare amendments — POST /mom/confirm/{token}/amendments */
    public function amendments(Request $request, string $token): RedirectResponse
    {
        $recipient = MomCirculationRecipient::withoutGlobalScopes()
            ->where('token', $token)
            ->firstOrFail();

        $circulation = $recipient->circulation()->withoutGlobalScopes()->firstOrFail();

        if ($locked = $this->lockedReason($circulation)) {
            return redirect()->route('mom.confirm', $token)
                ->with('error', $locked);
        }

        $recipient->update([
            'response' => 'amendment_requested',
            'responded_at' => now(),
            'responded_ip' => $request->ip(),
            'responded_user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('mom.confirm', $token)
            ->with('success', __('mom.amendment_success'));
    }

    /** Withdraw the guest's confirmation — DELETE /mom/confirm/{token} */
    public function destroy(Request $request, string $token): RedirectResponse
    {
        $recipient = MomCirculationRecipient::withoutGlobalScopes()
            ->where('token', $token)
            ->firstOrFail();

        $circulation = $recipient->circulation()->withoutGlobalScopes()->firstOrFail();

        if ($locked = $this->lockedReason($circulation)) {
            return redirect()->route('mom.confirm', $token)
                ->with('error', $locked);
        }

        $recipient->update([
            'response' => null,
            'responded_at' => null,
            'responded_ip' => null,
            'responded_user_agent' => null,
        ]);

        return redirect()->route('mom.confirm', $token)
            ->with('info', __('mom.withdraw_success'));
    }

    /**
     * Guests may only act while the circulation is genuinely still collecting
     * responses — not past its deadline, and not closed early by the secretary
     * (a MOM pulled back to draft must stop accruing confirmations).
     */
    private function lockedReason(MomCirculation $circulation): ?string
    {
        if (! $circulation->isOpen()) {
            return __('mom.circulation_closed');
        }

        if ($circulation->deadline_at->isPast()) {
            return __('mom.deadline_passed');
        }

        return null;
    }
}
