<?php

declare(strict_types=1);

namespace App\Domain\Attendee\Controllers;

use App\Domain\Attendee\Models\QrRegistrationToken;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\AttendeeRole;
use App\Support\Enums\RsvpStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QrRegistrationController extends Controller
{
    public function generate(MinutesOfMeeting $meeting): JsonResponse
    {
        $token = QrRegistrationToken::create([
            'minutes_of_meeting_id' => $meeting->id,
            'token' => Str::random(64),
            'is_active' => true,
            'expires_at' => now()->addHours(24),
        ]);

        $url = route('qr-registration.form', $token->token);

        return response()->json([
            'token' => $token->token,
            'url' => $url,
            'expires_at' => $token->expires_at->toIso8601String(),
        ]);
    }

    public function showForm(string $token): View
    {
        $qrToken = QrRegistrationToken::where('token', $token)->firstOrFail();

        if (! $qrToken->isValid()) {
            abort(410, 'This registration link has expired.');
        }

        $meeting = $qrToken->meeting;

        return view('qr-registration.form', compact('meeting', 'qrToken'));
    }

    public function register(Request $request, string $token): RedirectResponse
    {
        $qrToken = QrRegistrationToken::where('token', $token)->firstOrFail();

        if (! $qrToken->isValid()) {
            abort(410, 'This registration link has expired.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $meeting = $qrToken->meeting;

        $meeting->attendees()->create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'role' => AttendeeRole::Participant,
            'is_present' => true,
            'is_external' => true,
            'rsvp_status' => RsvpStatus::Accepted,
        ]);

        return redirect()->route('qr-registration.form', $token)
            ->with('success', 'You have been registered successfully!');
    }
}
