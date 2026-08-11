<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\AI\Jobs\ExtractMeetingDataJob;
use App\Domain\AI\Models\ProactiveInsight;
use App\Domain\AI\Services\ChatService;
use App\Domain\AI\Services\MeetingPrepBriefService;
use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Resources\Mobile\ExtractionResource;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiController extends MobileController
{
    public function __construct(
        private readonly ChatService $chatService,
        private readonly MeetingPrepBriefService $prepBriefService,
    ) {}

    public function extractions(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        return response()->json([
            'data' => ExtractionResource::collection($meeting->extractions()->latest()->get()),
        ]);
    }

    /**
     * Extraction is queued, not awaited: it takes far longer than a phone will
     * hold a request open. The app learns it finished from a push or by asking
     * for the extraction list again.
     */
    public function extract(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        ExtractMeetingDataJob::dispatch($meeting);

        return response()->json([
            'status' => 'queued',
            'message' => __('Extraction has started. You will be notified when it is ready.'),
        ], 202);
    }

    public function chat(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $reply = $this->chatService->sendMessage(
            $meeting,
            $this->user($request),
            $validated['message'],
        );

        return response()->json([
            'id' => $reply->id,
            'role' => $reply->role,
            'message' => $reply->message,
            'provider' => $reply->provider,
            'created_at' => $reply->created_at?->toIso8601String(),
        ]);
    }

    public function chatHistory(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $history = $this->chatService->getHistory($meeting, $this->user($request));

        return response()->json([
            'data' => $history->map(fn ($item) => [
                'id' => $item->id,
                'role' => $item->role,
                'message' => $item->message,
                'created_at' => $item->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function prepBrief(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $brief = $meeting->prepBriefs()
            ->where('user_id', $this->user($request)->id)
            ->latest()
            ->first();

        if ($brief === null) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $this->briefPayload($brief)]);
    }

    public function generatePrepBrief(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $brief = $this->prepBriefService->generateForUser($meeting, $this->user($request));

        return response()->json(['data' => $this->briefPayload($brief)], 201);
    }

    public function insights(Request $request): JsonResponse
    {
        $insights = ProactiveInsight::query()
            ->where('is_dismissed', false)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('generated_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $insights->map(fn (ProactiveInsight $insight) => [
                'id' => $insight->id,
                'type' => $insight->type,
                'title' => $insight->title,
                'description' => $insight->description,
                'severity' => $insight->severity,
                'is_read' => (bool) $insight->is_read,
                'generated_at' => $insight->generated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function readInsight(Request $request, ProactiveInsight $insight): JsonResponse
    {
        $insight->update(['is_read' => true]);

        return response()->json(null, 204);
    }

    public function dismissInsight(Request $request, ProactiveInsight $insight): JsonResponse
    {
        $insight->update(['is_dismissed' => true]);

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function briefPayload(mixed $brief): array
    {
        return [
            'id' => $brief->id,
            'meeting_id' => $brief->minutes_of_meeting_id,
            'content' => $brief->content,
            'summary_highlights' => $brief->summary_highlights,
            'estimated_prep_minutes' => $brief->estimated_prep_minutes,
            'generated_at' => $brief->generated_at?->toIso8601String(),
        ];
    }
}
