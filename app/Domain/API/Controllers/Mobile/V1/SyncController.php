<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Resources\Mobile\AttendeeResource;
use App\Domain\API\Resources\Mobile\DocumentResource;
use App\Domain\API\Resources\Mobile\MeetingDetailResource;
use App\Domain\API\Resources\Mobile\TranscriptionSegmentResource;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\Sync\MobileSyncService;
use App\Infrastructure\Sync\SyncCursor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SyncController extends MobileController
{
    public function __construct(
        private readonly MobileSyncService $syncService,
    ) {}

    public function pull(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['sometimes', 'nullable', 'string', 'max:512'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        $result = $this->syncService->pull(
            $this->user($request),
            SyncCursor::decode($validated['since'] ?? null),
            $validated['limit'] ?? 500,
        );

        return response()->json($result);
    }

    public function push(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'operations' => ['required', 'array', 'min:1', 'max:200'],
            'operations.*.client_id' => ['required', 'string', 'max:64'],
            'operations.*.entity' => ['required', Rule::in(['action_item', 'comment', 'manual_note'])],
            'operations.*.op' => ['required', Rule::in(['create', 'update'])],
            'operations.*.id' => ['required_if:operations.*.op,update', 'nullable', 'integer'],
            'operations.*.meeting_id' => ['sometimes', 'nullable', 'integer'],
            'operations.*.base_updated_at' => ['sometimes', 'nullable', 'date'],
            'operations.*.payload' => ['required', 'array'],
        ]);

        $user = $this->user($request);
        $results = [];

        foreach ($validated['operations'] as $operation) {
            $results[] = $this->syncService->apply($user, $operation);
        }

        return response()->json([
            'results' => $results,
            'cursor' => (new SyncCursor(now()))->encode(),
        ]);
    }

    /**
     * Everything needed to read a meeting with no connection at all.
     *
     * `pack_version` moves whenever the underlying content changes, so a device
     * can tell whether the copy it already has is still current without
     * downloading it again.
     */
    public function pack(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $meeting->load([
            'createdBy:id,name',
            'series:id,name',
            'attendees',
            'actionItems.assignedTo:id,name,avatar_path',
            'extractions',
            'resolutions.votes',
            'resolutions.mover:id,name',
            'resolutions.seconder:id,name',
            'documents',
            'voiceNotes.createdBy:id,name',
            'transcriptions',
            'tags',
            'liveSessions',
        ]);

        $transcript = $meeting->transcriptions()->latest('id')->first();

        return response()->json([
            'meeting' => new MeetingDetailResource($meeting),
            'attendees' => AttendeeResource::collection($meeting->attendees),
            'documents' => DocumentResource::collection($meeting->documents),
            'transcript' => $transcript === null ? null : [
                'id' => $transcript->id,
                'segments' => TranscriptionSegmentResource::collection(
                    $transcript->segments()->orderBy('sequence_order')->get()
                ),
            ],
            'pack_version' => $this->packVersion($meeting),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Derived rather than stored: the newest change across the parts that make
     * up a pack is exactly what "has this changed" means here.
     */
    private function packVersion(MinutesOfMeeting $meeting): string
    {
        $stamps = collect([
            $meeting->updated_at,
            $meeting->attendees->max('updated_at'),
            $meeting->actionItems->max('updated_at'),
            $meeting->documents->max('updated_at'),
            $meeting->extractions->max('updated_at'),
        ])->filter();

        return (string) ($stamps->max()?->getTimestamp() ?? 0);
    }
}
