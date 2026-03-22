<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Controllers;

use App\Domain\Calendar\Jobs\HandleMeetingStartJob;
use App\Domain\Calendar\Models\CalendarConnection;
use App\Domain\Calendar\Services\CalendarSyncService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CalendarWebhookController extends Controller
{
    public function __construct(
        private readonly CalendarSyncService $calendarSyncService,
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        $calendarProvider = $this->calendarSyncService->resolveProvider($provider);
        $calendarProvider->handleWebhook($request);

        $this->handleMeetingStartEvent($request, $provider);

        return response()->json(['status' => 'ok']);
    }

    private function handleMeetingStartEvent(Request $request, string $provider): void
    {
        $eventType = $request->input('event_type') ?? $request->input('type');

        if (! in_array($eventType, ['started', 'meeting.started', 'event.started'])) {
            return;
        }

        $meetingId = $request->input('meeting_id') ?? $request->input('resource_id');
        if (! $meetingId) {
            return;
        }

        $meeting = MinutesOfMeeting::find($meetingId);
        if (! $meeting) {
            return;
        }

        $connection = CalendarConnection::query()
            ->where('provider', $provider)
            ->where('organization_id', $meeting->organization_id)
            ->where('is_active', true)
            ->where('auto_record', true)
            ->first();

        if ($connection) {
            HandleMeetingStartJob::dispatch($meeting, $connection);
        }
    }
}
