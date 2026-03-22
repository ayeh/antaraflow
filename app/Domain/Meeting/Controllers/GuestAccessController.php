<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomGuestAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GuestAccessController
{
    /** Create a shareable link for a meeting */
    public function store(Request $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        abort_unless(
            $meeting->organization_id === $request->user()->current_organization_id,
            403
        );

        MomGuestAccess::create([
            'minutes_of_meeting_id' => $meeting->id,
            'organization_id' => $meeting->organization_id,
            'token' => Str::random(48),
            'label' => $request->input('label', 'Guest Link'),
            'email' => $request->input('email'),
            'is_active' => true,
            'expires_at' => $request->input('expires_at'),
        ]);

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Guest access link created.');
    }

    /** Revoke a guest access link */
    public function destroy(Request $request, MomGuestAccess $guestAccess): RedirectResponse
    {
        abort_unless(
            $guestAccess->organization_id === $request->user()->current_organization_id,
            403
        );

        $meetingId = $guestAccess->minutes_of_meeting_id;
        $guestAccess->delete();

        return redirect()->route('meetings.show', $meetingId)
            ->with('success', 'Guest access link revoked.');
    }

    /** Public guest view — no auth required */
    public function show(string $token): View
    {
        $access = MomGuestAccess::withoutGlobalScopes()
            ->where('token', $token)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->firstOrFail();

        $access->increment('access_count');
        $access->update(['last_accessed_at' => now()]);

        $meeting = $access->meeting()->withoutGlobalScopes()->with([
            'attendees',
            'actionItems' => fn ($q) => $q->where('client_visible', true),
        ])->firstOrFail();

        $comments = Comment::withoutGlobalScopes()
            ->where('commentable_type', MinutesOfMeeting::class)
            ->where('commentable_id', $meeting->id)
            ->where('client_visible', true)
            ->with('user')
            ->get();

        return view('meetings.guest', compact('meeting', 'access', 'comments'));
    }
}
