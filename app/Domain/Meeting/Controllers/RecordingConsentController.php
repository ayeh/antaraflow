<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\RecordingConsent;
use App\Domain\Meeting\Requests\StoreRecordingConsentRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class RecordingConsentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Record that the person about to record has acknowledged the recording
     * notice and taken responsibility for informing participants.
     *
     * The consent gate is shown once per meeting, so this is a durable, per
     * meeting audit record: who acknowledged, when, which notice wording, and
     * whether an invisible tab-audio capture was involved. The owning
     * organisation is taken from the meeting, not the acting user, so the trail
     * belongs to the meeting's tenant even if that ever diverges.
     */
    public function store(StoreRecordingConsentRequest $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        RecordingConsent::createForOrganization($meeting->organization_id, [
            'minutes_of_meeting_id' => $meeting->id,
            'acknowledged_by' => $request->user()->id,
            'notice_version' => $request->string('notice_version')->toString(),
            'includes_tab_audio' => $request->boolean('includes_tab_audio'),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
            'acknowledged_at' => now(),
        ]);

        return response()->json(['consented' => true], 201);
    }
}
