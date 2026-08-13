<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\Account\Services\AuditService;
use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\Collaboration\Services\CommentService;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Minutes waiting on this person's response.
 *
 * Approving from a phone is the single most common thing a board member is
 * asked to do between meetings, and today it happens through a tokenised email
 * link. Here it is answered as the signed-in user instead, which is both easier
 * and a stronger record of who actually responded.
 */
class CirculationController extends MobileController
{
    public function __construct(
        private readonly CommentService $commentService,
        private readonly AuditService $auditService,
    ) {}

    public function pending(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $recipients = MomCirculationRecipient::query()
            ->whereNull('response')
            ->where(fn ($query) => $query->where('email', $user->email))
            ->with(['circulation.meeting:id,title,mom_number,meeting_date,status'])
            ->get();

        return response()->json([
            'data' => $recipients
                ->filter(fn (MomCirculationRecipient $recipient) => $recipient->circulation?->isOpen() === true)
                ->map(fn (MomCirculationRecipient $recipient) => $this->payload($recipient))
                ->values(),
        ]);
    }

    public function respond(Request $request, MomCirculationRecipient $recipient): JsonResponse
    {
        $user = $this->user($request);

        if (! hash_equals((string) $recipient->email, (string) $user->email)) {
            return $this->failure(
                __('This circulation was not sent to you.'),
                'FORBIDDEN',
                403,
            );
        }

        $circulation = $recipient->circulation;

        if ($circulation === null) {
            return $this->failure(__('Not found.'), 'NOT_FOUND', 404);
        }

        if (! $circulation->isOpen()) {
            return $this->failure(__('mom.circulation_closed'), 'CIRCULATION_CLOSED', 409);
        }

        if ($circulation->deadline_at?->isPast()) {
            return $this->failure(__('The deadline for this circulation has passed.'), 'CIRCULATION_CLOSED', 409);
        }

        if ($recipient->hasResponded()) {
            return $this->failure(__('You have already responded to this circulation.'), 'ALREADY_RESPONDED', 409);
        }

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['confirmed', 'amendment'])],
            'remark' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'attestation' => ['sometimes', 'array'],
            'attestation.method' => ['sometimes', Rule::in(['biometric', 'password', 'none'])],
            'attestation.device_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $recipient->update([
            'response' => $validated['decision'],
            'responded_at' => now(),
            'responded_ip' => $request->ip(),
            'responded_user_agent' => $request->userAgent(),
        ]);

        if (! empty($validated['remark'])) {
            $meeting = $circulation->meeting;

            if ($meeting !== null) {
                $this->commentService->addComment($meeting, $user, $validated['remark'], null);
            }
        }

        // The attestation method is a claim made by the app, not proof. It is
        // recorded as context for the audit trail and must not be presented as
        // a signature.
        $this->auditService->log('circulation.responded', $recipient, null, [
            'decision' => $validated['decision'],
            'attestation_method' => $validated['attestation']['method'] ?? 'none',
            'device_id' => $validated['attestation']['device_id'] ?? null,
            'client_version' => $request->header('X-Client-Version'),
        ]);

        return response()->json($this->payload($recipient->fresh()));
    }

    /** @return array<string, mixed> */
    private function payload(MomCirculationRecipient $recipient): array
    {
        $circulation = $recipient->circulation;
        $meeting = $circulation?->meeting;

        return [
            'id' => $recipient->id,
            'circulation_id' => $recipient->mom_circulation_id,
            'subject' => $circulation?->subject,
            'round' => $circulation?->round,
            'deadline_at' => $circulation?->deadline_at?->toIso8601String(),
            'response' => $recipient->response,
            'responded_at' => $recipient->responded_at?->toIso8601String(),
            'meeting' => $meeting === null ? null : [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'mom_number' => $meeting->mom_number,
                'meeting_date' => $meeting->meeting_date?->toIso8601String(),
                'status' => $meeting->status->value,
            ],
            'deep_link' => "antaraflow://circulations/{$recipient->id}",
        ];
    }
}
