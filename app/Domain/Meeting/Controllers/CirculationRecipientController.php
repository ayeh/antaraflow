<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Mail\CirculationInviteMail;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CirculationRecipientController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, MomCirculation $circulation): RedirectResponse
    {
        $this->authorize('update', $circulation->meeting);

        abort_if(! $circulation->isOpen(), 403, 'Circulation is closed.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $recipient = MomCirculationRecipient::create([
            'mom_circulation_id' => $circulation->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'token' => Str::random(64),
        ]);

        Mail::to($recipient->email)->send(new CirculationInviteMail($recipient));

        return redirect()->route('meetings.show', $circulation->meeting)
            ->with('success', __('mom.recipient_added', ['name' => $recipient->name]));
    }

    public function destroy(MomCirculation $circulation, MomCirculationRecipient $recipient): RedirectResponse
    {
        $this->authorize('update', $circulation->meeting);

        abort_if(! $circulation->isOpen(), 403, 'Circulation is closed.');
        abort_if($recipient->mom_circulation_id !== $circulation->id, 404);
        abort_if($recipient->hasResponded(), 422, 'Cannot remove a recipient who has already responded.');

        $recipient->delete();

        return redirect()->route('meetings.show', $circulation->meeting)
            ->with('success', __('mom.recipient_removed', ['name' => $recipient->name]));
    }
}
