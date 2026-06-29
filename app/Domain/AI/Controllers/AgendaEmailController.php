<?php

declare(strict_types=1);

namespace App\Domain\AI\Controllers;

use App\Domain\AI\Mail\AgendaEmail;
use App\Domain\AI\Requests\SendAgendaEmailRequest;
use App\Domain\AI\Services\AgendaEmailService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AgendaEmailController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private AgendaEmailService $agendaEmailService,
    ) {}

    public function generate(MinutesOfMeeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $data = $this->agendaEmailService->generate($meeting);

        $orgMembers = User::query()
            ->where('current_organization_id', $meeting->organization_id)
            ->whereNotNull('email')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('meetings.agenda-email', [
            'meeting' => $meeting,
            'subject' => $data['subject'],
            'body' => $data['body'],
            'recipients' => $data['recipients'],
            'orgMembers' => $orgMembers,
        ]);
    }

    public function send(SendAgendaEmailRequest $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->authorize('view', $meeting);

        $validated = $request->validated();

        $mailable = new AgendaEmail(
            emailSubject: $validated['subject'],
            emailBody: $validated['body'],
            meetingTitle: $meeting->title,
            meetingDate: $meeting->meeting_date?->format('F j, Y'),
            meetingUrl: route('meetings.show', $meeting),
        );

        Mail::to($validated['recipients'])->send($mailable);

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Agenda email sent successfully to '.count($validated['recipients']).' recipient(s).');
    }
}
