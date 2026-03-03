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
    public function generate(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'expires_at' => ['nullable', 'date', 'after:now'],
            'max_attendees' => ['nullable', 'integer', 'min:1'],
            'required_fields' => ['nullable', 'array'],
            'required_fields.*' => ['string', 'in:name,email,phone,company'],
            'welcome_message' => ['nullable', 'string', 'max:500'],
        ]);

        // Deactivate any existing tokens for this meeting
        QrRegistrationToken::where('minutes_of_meeting_id', $meeting->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $joinCode = strtoupper(Str::random(6));

        $token = QrRegistrationToken::create([
            'minutes_of_meeting_id' => $meeting->id,
            'token' => Str::random(64),
            'join_code' => $joinCode,
            'is_active' => true,
            'expires_at' => $validated['expires_at'] ?? now()->addHours(24),
            'max_attendees' => $validated['max_attendees'] ?? null,
            'required_fields' => $validated['required_fields'] ?? ['name'],
            'welcome_message' => $validated['welcome_message'] ?? null,
            'registrations_count' => 0,
        ]);

        $url = route('qr-registration.form', $token->token);

        return response()->json([
            'token' => $token->token,
            'join_code' => $token->join_code,
            'url' => $url,
            'expires_at' => $token->expires_at->toIso8601String(),
            'max_attendees' => $token->max_attendees,
            'required_fields' => $token->required_fields,
            'welcome_message' => $token->welcome_message,
            'registrations_count' => $token->registrations_count,
            'is_active' => $token->is_active,
        ]);
    }

    public function disable(MinutesOfMeeting $meeting): JsonResponse
    {
        QrRegistrationToken::where('minutes_of_meeting_id', $meeting->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return response()->json(['message' => 'QR registration disabled.']);
    }

    public function showForm(string $token): View
    {
        $qrToken = QrRegistrationToken::where('token', $token)->firstOrFail();

        if (! $qrToken->isValid()) {
            abort(410, 'This registration link has expired.');
        }

        if ($qrToken->isFull()) {
            abort(410, 'Registration is full. No more spots available.');
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

        if ($qrToken->isFull()) {
            abort(410, 'Registration is full. No more spots available.');
        }

        $requiredFields = $qrToken->required_fields ?? ['name'];
        $rules = [];

        if (in_array('name', $requiredFields)) {
            $rules['name'] = ['required', 'string', 'max:255'];
        } else {
            $rules['name'] = ['nullable', 'string', 'max:255'];
        }

        if (in_array('email', $requiredFields)) {
            $rules['email'] = ['required', 'email', 'max:255'];
        } else {
            $rules['email'] = ['nullable', 'email', 'max:255'];
        }

        if (in_array('phone', $requiredFields)) {
            $rules['phone'] = ['required', 'string', 'max:20'];
        } else {
            $rules['phone'] = ['nullable', 'string', 'max:20'];
        }

        if (in_array('company', $requiredFields)) {
            $rules['company'] = ['required', 'string', 'max:255'];
        } else {
            $rules['company'] = ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);

        $meeting = $qrToken->meeting;

        $meeting->attendees()->create([
            'name' => $validated['name'] ?? 'Guest',
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'company' => $validated['company'] ?? null,
            'role' => AttendeeRole::Participant,
            'is_present' => true,
            'is_external' => true,
            'rsvp_status' => RsvpStatus::Accepted,
        ]);

        $qrToken->incrementRegistrations();

        return redirect()->route('qr-registration.success', $token)
            ->with('registration', [
                'name' => $validated['name'] ?? 'Guest',
                'email' => $validated['email'] ?? null,
            ]);
    }

    public function success(string $token): View
    {
        $qrToken = QrRegistrationToken::where('token', $token)->firstOrFail();
        $meeting = $qrToken->meeting;
        $registration = session('registration', []);

        return view('qr-registration.success', compact('meeting', 'qrToken', 'registration'));
    }
}
