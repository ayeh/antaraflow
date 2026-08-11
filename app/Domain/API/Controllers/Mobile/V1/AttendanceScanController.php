<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Resources\Mobile\AttendeeResource;
use App\Domain\Attendee\Models\QrRegistrationToken;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\AttendeeRole;
use App\Support\Enums\RsvpStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Check-in by QR code.
 *
 * Two sides of the same feature: the person running the meeting shows a code
 * from their phone, and everyone else scans it with theirs. The scan side is
 * deliberately reachable by any signed-in user — the token is the grant, and an
 * attendee is frequently not a member of the host organisation.
 */
class AttendanceScanController extends MobileController
{
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'company' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);

        return DB::transaction(function () use ($validated, $request): JsonResponse {
            $token = QrRegistrationToken::query()
                ->where('token', $validated['token'])
                ->lockForUpdate()
                ->first();

            if ($token === null) {
                return $this->failure(__('This registration code is not valid.'), 'QR_TOKEN_INVALID', 404);
            }

            if (! $token->isValid()) {
                return $this->failure(__('This registration link has expired.'), 'QR_TOKEN_EXPIRED', 410);
            }

            if ($token->isFull()) {
                return $this->failure(__('Registration is full. No more spots available.'), 'QR_TOKEN_FULL', 410);
            }

            $meeting = $token->meeting;

            if ($meeting === null) {
                return $this->failure(__('This meeting no longer exists.'), 'NOT_FOUND', 410);
            }

            $user = $this->user($request);
            $email = $validated['email'] ?? $user->email;

            $existing = $meeting->attendees()
                ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('email', $email))
                ->first();

            if ($existing !== null) {
                // Scanning twice should read as "you're checked in", not as an error.
                $existing->update(['is_present' => true, 'rsvp_status' => RsvpStatus::Accepted]);

                return response()->json([
                    'already_registered' => true,
                    'meeting' => $this->meetingSummary($meeting),
                    'attendee' => new AttendeeResource($existing->fresh()),
                ]);
            }

            $attendee = $meeting->attendees()->create([
                'user_id' => $user->id,
                'name' => $validated['name'] ?? $user->name,
                'email' => $email,
                'phone' => $validated['phone'] ?? null,
                'company' => $validated['company'] ?? null,
                'position' => $validated['position'] ?? null,
                'role' => AttendeeRole::Participant,
                'is_present' => true,
                'is_external' => $meeting->organization_id !== $user->current_organization_id,
                'rsvp_status' => RsvpStatus::Accepted,
            ]);

            $token->incrementRegistrations();

            return response()->json([
                'already_registered' => false,
                'meeting' => $this->meetingSummary($meeting),
                'attendee' => new AttendeeResource($attendee),
            ], 201);
        });
    }

    public function show(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $token = $meeting->qrRegistrationTokens()->where('is_active', true)->first();

        if ($token === null) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            ...$this->tokenPayload($token),
            'registered' => AttendeeResource::collection(
                $meeting->attendees()->where('is_present', true)->latest()->get()
            ),
        ]);
    }

    public function store(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'max_attendees' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'welcome_message' => ['sometimes', 'nullable', 'string', 'max:500'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $meeting->qrRegistrationTokens()->where('is_active', true)->update(['is_active' => false]);

        $token = $meeting->qrRegistrationTokens()->create([
            'token' => Str::random(64),
            'join_code' => strtoupper(Str::random(6)),
            'is_active' => true,
            'expires_at' => $validated['expires_at'] ?? null,
            'max_attendees' => $validated['max_attendees'] ?? null,
            'required_fields' => ['name'],
            'welcome_message' => $validated['welcome_message'] ?? null,
            'registrations_count' => 0,
        ]);

        return response()->json($this->tokenPayload($token), 201);
    }

    public function destroy(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $meeting->qrRegistrationTokens()->where('is_active', true)->update(['is_active' => false]);

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function tokenPayload(QrRegistrationToken $token): array
    {
        return [
            'token' => $token->token,
            'join_code' => $token->join_code,
            // What the host's phone renders as a QR code.
            'qr_payload' => route('qr-registration.form', $token->token),
            'expires_at' => $token->expires_at?->toIso8601String(),
            'max_attendees' => $token->max_attendees,
            'registrations_count' => $token->registrations_count,
            'welcome_message' => $token->welcome_message,
        ];
    }

    /** @return array<string, mixed> */
    private function meetingSummary(MinutesOfMeeting $meeting): array
    {
        return [
            'id' => $meeting->id,
            'title' => $meeting->title,
            'meeting_date' => $meeting->meeting_date?->toIso8601String(),
            'location' => $meeting->location,
        ];
    }
}
