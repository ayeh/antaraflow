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

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        MomGuestAccess::create([
            'minutes_of_meeting_id' => $meeting->id,
            'organization_id' => $meeting->organization_id,
            'token' => Str::random(48),
            'label' => $validated['label'] ?? 'Guest Link',
            'email' => $validated['email'] ?? null,
            'is_active' => true,
            'expires_at' => $validated['expires_at'] ?? null,
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
            'attendees' => fn ($q) => $q->select('id', 'minutes_of_meeting_id', 'name', 'role'),
            'topics' => fn ($q) => $q->orderBy('sort_order'),
            'actionItems' => fn ($q) => $q->where('client_visible', true)->with('assignedTo:id,name'),
        ])->firstOrFail();

        $comments = Comment::withoutGlobalScopes()
            ->where('commentable_type', MinutesOfMeeting::class)
            ->where('commentable_id', $meeting->id)
            ->where('client_visible', true)
            ->with('user:id,name')
            ->get();

        return view('meetings.guest', compact('meeting', 'access', 'comments'));
    }
}
