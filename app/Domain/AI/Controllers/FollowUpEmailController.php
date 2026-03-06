<?php

declare(strict_types=1);

namespace App\Domain\AI\Controllers;

use App\Domain\AI\Mail\FollowUpMeetingEmail;
use App\Domain\AI\Requests\SendFollowUpEmailRequest;
use App\Domain\AI\Services\FollowUpEmailService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class FollowUpEmailController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private FollowUpEmailService $followUpEmailService,
    ) {}

    public function generate(MinutesOfMeeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $data = $this->followUpEmailService->generate($meeting);

        return view('meetings.follow-up-email', [
            'meeting' => $meeting,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'recipients' => $data['recipients'],
        ]);
    }

    public function send(SendFollowUpEmailRequest $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->authorize('view', $meeting);

        $validated = $request->validated();

        $meetingUrl = route('meetings.show', $meeting);

        $mailable = new FollowUpMeetingEmail(
            emailSubject: $validated['subject'],
            emailBody: $validated['body'],
            meetingTitle: $meeting->title,
            meetingUrl: $meetingUrl,
        );

        Mail::to($validated['recipients'])->send($mailable);

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Follow-up email sent successfully to '.count($validated['recipients']).' recipient(s).');
    }
}
