<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\API\Resources\Mobile\ExtractionResource;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Models\LiveBookmark;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Services\LiveMeetingService;
use App\Domain\LiveMeeting\Support\AudioChunkFormats;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\BookmarkKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LiveSessionController extends MobileController
{
    public function __construct(
        private readonly LiveMeetingService $liveMeetingService,
    ) {}

    public function start(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('startLive', $meeting);

        $config = $request->validate([
            'chunk_interval' => ['sometimes', 'integer', 'min:10', 'max:120'],
            'extraction_interval' => ['sometimes', 'integer', 'min:60', 'max:600'],
            'live_extraction' => ['sometimes', 'boolean'],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);
        unset($config['client_id']);

        try {
            $session = $this->liveMeetingService->startSession($meeting, $this->user($request), $config);
        } catch (\RuntimeException $e) {
            $active = LiveMeetingSession::query()
                ->where('minutes_of_meeting_id', $meeting->id)
                ->where('status', LiveSessionStatus::Active)
                ->first();

            // Returned rather than refused outright: an app that was killed
            // mid-meeting has to be able to rejoin the session it started.
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'SESSION_ALREADY_ACTIVE',
                'session' => $active ? $this->sessionPayload($active) : null,
                'resume' => $active ? $this->liveMeetingService->getResumeState($active) : null,
            ], 409);
        }

        return response()->json([
            'session' => $this->sessionPayload($session),
            'broadcast' => $this->broadcastPayload($session),
            'upload' => [
                'max_chunk_bytes' => AudioChunkFormats::MAX_KILOBYTES * 1024,
                'accepted_mimetypes' => AudioChunkFormats::MIMETYPES,
            ],
        ], 201);
    }

    public function chunk(Request $request, LiveMeetingSession $session): JsonResponse
    {
        $this->authorizeSession($session, 'startLive');

        if (! $session->isActive()) {
            return $this->failure(__('This session is not active.'), 'SESSION_NOT_ACTIVE', 409);
        }

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:'.AudioChunkFormats::MAX_KILOBYTES, 'mimetypes:'.implode(',', AudioChunkFormats::MIMETYPES)],
            'chunk_number' => ['required', 'integer', 'min:0'],
            'start_time' => ['required', 'numeric', 'min:0'],
            'end_time' => ['required', 'numeric', 'gt:start_time'],
            'checksum' => ['sometimes', 'nullable', 'string', 'max:128'],
            // dBFS, so never above zero and never usefully below the floor the
            // device reports for digital silence. Bounded rather than trusted:
            // a client sending nonsense here would poison the only evidence we
            // have about capture quality.
            'peak_dbfs' => ['sometimes', 'numeric', 'between:-100,0'],
            'speech_dbfs' => ['sometimes', 'numeric', 'between:-100,0'],
            'noise_dbfs' => ['sometimes', 'numeric', 'between:-100,0'],
        ]);

        $existing = $this->liveMeetingService->findExistingChunk($session, (int) $validated['chunk_number']);

        if ($existing !== null) {
            // Not an error: the client should drop this from its queue and move
            // on, which is exactly what it does for a 2xx.
            return response()->json([
                'code' => 'CHUNK_DUPLICATE',
                'chunk' => $this->chunkPayload($existing),
                'next_chunk_number' => $this->liveMeetingService->getResumeState($session)['next_chunk_number'],
            ]);
        }

        $chunk = $this->liveMeetingService->processChunk(
            $session,
            $request->file('audio'),
            (int) $validated['chunk_number'],
            (float) $validated['start_time'],
            (float) $validated['end_time'],
            array_map(
                static fn ($level): float => (float) $level,
                array_intersect_key($validated, array_flip(['peak_dbfs', 'speech_dbfs', 'noise_dbfs'])),
            ),
        );

        return response()->json([
            'chunk' => $this->chunkPayload($chunk),
            'next_chunk_number' => $chunk->chunk_number + 1,
        ], 201);
    }

    public function state(Request $request, LiveMeetingSession $session): JsonResponse
    {
        $this->authorizeSession($session, 'view');

        $sinceChunk = $request->filled('since_chunk') ? $request->integer('since_chunk') : null;
        $state = $this->liveMeetingService->getSessionState($session, $sinceChunk);
        $resume = $this->liveMeetingService->getResumeState($session);

        return response()->json([
            'session' => $this->sessionPayload($session),
            'next_chunk_number' => $resume['next_chunk_number'],
            'missing_chunks' => $resume['missing_chunks'],
            'stats' => $resume['stats'],
            'chunks' => $state['chunks']->map(fn ($chunk) => $this->chunkPayload($chunk)),
            'extractions' => ExtractionResource::collection($state['extractions']),
            'bookmarks' => $this->bookmarks($session),
        ]);
    }

    public function pause(Request $request, LiveMeetingSession $session): JsonResponse
    {
        $this->authorizeSession($session, 'startLive');

        if (! $session->isActive()) {
            return $this->failure(__('This session is not active.'), 'SESSION_NOT_ACTIVE', 409);
        }

        $this->liveMeetingService->pauseSession($session);

        return response()->json(['session' => $this->sessionPayload($session->fresh())]);
    }

    public function resume(Request $request, LiveMeetingSession $session): JsonResponse
    {
        $this->authorizeSession($session, 'startLive');

        if ($session->status !== LiveSessionStatus::Paused) {
            return $this->failure(__('This session is not paused.'), 'SESSION_NOT_PAUSED', 409);
        }

        $this->liveMeetingService->resumeSession($session);

        return response()->json([
            'session' => $this->sessionPayload($session->fresh()),
            'resume' => $this->liveMeetingService->getResumeState($session),
        ]);
    }

    public function extraction(Request $request, LiveMeetingSession $session): JsonResponse
    {
        $this->authorizeSession($session, 'startLive');

        if (! $session->isActive()) {
            return $this->failure(__('This session is not active.'), 'SESSION_NOT_ACTIVE', 409);
        }

        $enabled = $request->validate(['enabled' => ['required', 'boolean']])['enabled'];

        $session->update(['config' => [...$session->config ?? [], 'live_extraction' => $enabled]]);

        return response()->json([
            'live_extraction' => $enabled,
            'message' => $enabled
                ? __('Live AI extraction is on. It will update every few minutes.')
                : __('Live AI extraction is off.'),
        ]);
    }

    public function end(Request $request, LiveMeetingSession $session): JsonResponse
    {
        $this->authorizeSession($session, 'startLive');

        if ($session->status === LiveSessionStatus::Ended) {
            return $this->failure(__('This session has already ended.'), 'SESSION_NOT_ACTIVE', 409);
        }

        $this->liveMeetingService->endSession($session);

        $session->refresh();
        $resume = $this->liveMeetingService->getResumeState($session);

        return response()->json([
            'session' => $this->sessionPayload($session),
            'transcription_id' => $session->meeting->transcriptions()->latest('id')->value('id'),
            'chunks_dropped' => $resume['stats']['chunks_total'] - $resume['stats']['chunks_completed'],
        ]);
    }

    public function storeBookmark(Request $request, LiveMeetingSession $session): JsonResponse
    {
        $this->authorizeSession($session, 'startLive');

        $validated = $request->validate([
            'at_seconds' => ['required', 'numeric', 'min:0'],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'kind' => ['sometimes', Rule::enum(BookmarkKind::class)],
            'client_id' => ['sometimes', 'string', 'max:64'],
        ]);

        $bookmark = LiveBookmark::query()->create([
            'live_meeting_session_id' => $session->id,
            'created_by' => $this->user($request)->id,
            'at_seconds' => $validated['at_seconds'],
            'label' => $validated['label'] ?? null,
            'kind' => $validated['kind'] ?? BookmarkKind::General->value,
        ]);

        return response()->json(['bookmark' => $this->bookmarkPayload($bookmark)], 201);
    }

    public function destroyBookmark(Request $request, LiveBookmark $bookmark): JsonResponse
    {
        $this->authorizeSession($bookmark->session, 'startLive');

        $bookmark->delete();

        return response()->json(null, 204);
    }

    private function authorizeSession(LiveMeetingSession $session, string $ability): void
    {
        $meeting = MinutesOfMeeting::query()->find($session->minutes_of_meeting_id);

        // The scope hides meetings in other tenants, so a missing meeting here
        // means the session belongs to someone else.
        abort_if($meeting === null, 404);

        $this->authorize($ability, $meeting);
    }

    /** @return array<string, mixed> */
    private function sessionPayload(LiveMeetingSession $session): array
    {
        return [
            'id' => $session->id,
            'meeting_id' => $session->minutes_of_meeting_id,
            'status' => $session->status->value,
            'config' => $session->config,
            'started_at' => $session->started_at?->toIso8601String(),
            'paused_at' => $session->paused_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
            'total_duration_seconds' => $session->total_duration_seconds,
        ];
    }

    /** @return array<string, mixed> */
    private function broadcastPayload(LiveMeetingSession $session): array
    {
        return [
            'channel' => "private-live-meeting.{$session->id}",
            'events' => [
                'TranscriptionChunkProcessed',
                'LiveExtractionUpdated',
                'LiveSessionEnded',
                'LiveTranscriptIncomplete',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function chunkPayload(mixed $chunk): array
    {
        return [
            'id' => $chunk->id,
            'chunk_number' => $chunk->chunk_number,
            'text' => $chunk->text,
            'speaker' => $chunk->speaker,
            'start_time' => (float) $chunk->start_time,
            'end_time' => (float) $chunk->end_time,
            'confidence' => $chunk->confidence !== null ? (float) $chunk->confidence : null,
            'status' => $chunk->status->value,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function bookmarks(LiveMeetingSession $session): \Illuminate\Support\Collection
    {
        return LiveBookmark::query()
            ->where('live_meeting_session_id', $session->id)
            ->orderBy('at_seconds')
            ->get()
            ->map(fn (LiveBookmark $bookmark) => $this->bookmarkPayload($bookmark));
    }

    /** @return array<string, mixed> */
    private function bookmarkPayload(LiveBookmark $bookmark): array
    {
        return [
            'id' => $bookmark->id,
            'at_seconds' => $bookmark->at_seconds,
            'label' => $bookmark->label,
            'kind' => $bookmark->kind->value,
            'created_at' => $bookmark->created_at?->toIso8601String(),
        ];
    }
}
