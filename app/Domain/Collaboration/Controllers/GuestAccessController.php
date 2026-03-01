<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Controllers;

use App\Domain\Collaboration\Models\MeetingShare;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class GuestAccessController extends Controller
{
    public function show(string $token, Request $request): View
    {
        $share = MeetingShare::query()
            ->with(['meeting.attendees.user', 'meeting.actionItems.assignedTo', 'meeting.createdBy'])
            ->where('share_token', $token)
            ->whereNull('shared_with_user_id')
            ->firstOrFail();

        abort_if($share->isExpired(), 410, 'This share link has expired.');

        $meeting = $share->meeting;

        return view('guest.meeting-view', compact('meeting', 'share'));
    }
}
