<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Services;

use App\Domain\AI\Jobs\ExtractMeetingDataJob;
use App\Domain\LiveMeeting\Enums\ChunkRole;
use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Events\LiveTranscriptIncomplete;
use App\Domain\LiveMeeting\Jobs\LiveTranscriptionJob;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Jobs\DiarizeTranscriptionJob;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Models\TranscriptionSegment;
use App\Models\User;
use App\Support\Enums\InputType;
use App\Support\Enums\TranscriptionStatus;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiveMeetingService
{
    /**
     * Below this, in dBFS, the primary did not hear that moment well enough to
     * be trusted on its own and a satellite covering it earns its transcription.
     *
     * The same figure the recorder warns the room on (`RoomLevel.faintBelow`),
     * and provisional for the same reason: it was set from where distant
     * speech tends to land rather than from measurement. The readings stored
     * on every chunk are how it gets replaced with a number from real rooms.
     */
    private const FAINT_DBFS = -45.0;

    /**
     * @param  array<string, mixed>  $config
     */
    public function startSession(MinutesOfMeeting $meeting, User $user, array $config = []): LiveMeetingSession
    {
        return DB::transaction(function () use ($meeting, $user, $config): LiveMeetingSession {
            $existingActiveSession = LiveMeetingSession::query()
                ->where('minutes_of_meeting_id', $meeting->id)
                ->where('status', LiveSessionStatus::Active)
                ->lockForUpdate()
                ->exists();

            if ($existingActiveSession) {
                throw new \RuntimeException(__('Meeting already has an active live session.'));
            }

            // Live extraction is opt-in: it costs about what a full set of
            // minutes costs, once per interval, on top of transcription.
            $defaultConfig = [
                'chunk_interval' => 30,
                'extraction_interval' => 300,
                'live_extraction' => false,
            ];

            return LiveMeetingSession::query()->create([
                'minutes_of_meeting_id' => $meeting->id,
                'started_by' => $user->id,
                'status' => LiveSessionStatus::Active,
                'config' => array_merge($defaultConfig, $config),
                'started_at' => now(),
            ]);
        });
    }

    public function endSession(LiveMeetingSession $session): void
    {
        $endedAt = now();
        $totalDuration = (int) $session->started_at->diffInSeconds($endedAt);

        $session->update([
            'status' => LiveSessionStatus::Ended,
            'ended_at' => $endedAt,
            'total_duration_seconds' => $totalDuration,
        ]);

        $transcription = $this->mergeChunksIntoTranscription($session);

        // Dispatched alongside extraction rather than before it. Extraction
        // reads `full_text`, which the names never touch, so making the
        // minutes wait on a language model would be delay for nothing.
        if ($transcription !== null) {
            DiarizeTranscriptionJob::dispatch($transcription);
        }

        ExtractMeetingDataJob::dispatch($session->meeting);
    }

    public function pauseSession(LiveMeetingSession $session): void
    {
        $session->update([
            'status' => LiveSessionStatus::Paused,
            'paused_at' => now(),
        ]);
    }

    public function resumeSession(LiveMeetingSession $session): void
    {
        $session->update([
            'status' => LiveSessionStatus::Active,
            'paused_at' => null,
        ]);
    }

    /**
     * A chunk already held for this session, if any.
     *
     * Mobile clients retry from a durable upload queue, so the same chunk will
     * arrive twice whenever a response is lost in flight. Recognising it lets
     * the caller answer "already have it" instead of storing the audio twice
     * and transcribing — and billing — it again.
     */
    public function findExistingChunk(
        LiveMeetingSession $session,
        int $chunkNumber,
        string $deviceId = '',
    ): ?LiveTranscriptChunk {
        return $session->chunks()
            ->where('device_id', $deviceId)
            ->where('chunk_number', $chunkNumber)
            ->first();
    }

    /**
     * @param  array{peak_dbfs?: float, speech_dbfs?: float, noise_dbfs?: float}  $levels
     *                                                                                     How loud the device measured this chunk to be. Empty from any client
     *                                                                                     that does not measure, which is every one of them before the app
     *                                                                                     learned to.
     */
    public function processChunk(
        LiveMeetingSession $session,
        UploadedFile $file,
        int $chunkNumber,
        float $startTime,
        float $endTime,
        array $levels = [],
        string $deviceId = '',
        ChunkRole $role = ChunkRole::Primary,
    ): LiveTranscriptChunk {
        $orgId = $session->meeting->organization_id;
        $path = "organizations/{$orgId}/audio/live/{$session->id}";

        // The device belongs in the filename now that two of them write here.
        // Without it the satellite's chunk 4 overwrites the primary's on disk
        // while both rows survive, and the row that lost points at audio that
        // is not its own.
        $storedPath = $file->storeAs(
            $path,
            sprintf(
                'chunk_%05d%s.%s',
                $chunkNumber,
                $deviceId === '' ? '' : '_'.substr(sha1($deviceId), 0, 8),
                $file->getClientOriginalExtension(),
            ),
            'local',
        );

        try {
            $chunk = LiveTranscriptChunk::query()->create([
                'live_meeting_session_id' => $session->id,
                'device_id' => $deviceId,
                'role' => $role,
                'chunk_number' => $chunkNumber,
                'audio_file_path' => $storedPath,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => ChunkStatus::Pending,
                ...array_intersect_key($levels, array_flip(['peak_dbfs', 'speech_dbfs', 'noise_dbfs'])),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Two retries of the same chunk racing. The caller's earlier
            // duplicate check passed for both, which it always could — it just
            // used to produce a second row and a second transcription bill
            // instead of landing here.
            //
            // Answered as the duplicate it is. A failure would be far worse
            // than a wasted upload: the mobile outbox retries anything that is
            // not a 2xx forever, and would hold every later chunk of the
            // meeting behind this one.
            $existing = $this->findExistingChunk($session, $chunkNumber, $deviceId);

            if ($existing !== null) {
                return $existing;
            }

            throw new \RuntimeException('A chunk collided with itself and then vanished.');
        }

        if ($role === ChunkRole::Satellite && ! $this->satelliteIsNeeded($session, $chunkNumber)) {
            $chunk->update(['status' => ChunkStatus::Skipped]);

            return $chunk;
        }

        LiveTranscriptionJob::dispatch($chunk);

        return $chunk;
    }

    /**
     * Whether a satellite chunk is worth transcribing.
     *
     * A satellite doubles the transcription bill for every moment it covers,
     * and most of those moments the primary heard perfectly well. The level
     * the primary measured for the same window is the cheap way to tell, and
     * it is available the instant the primary's chunk is stored — no waiting
     * on a transcriber.
     *
     * Confidence would be a better signal and is deliberately not used: it
     * only exists after transcription, and waiting for it would mean holding
     * every satellite chunk pending behind a job, then releasing it from an
     * event. That machinery is worth building only if the levels turn out not
     * to separate these cases well, which is one of the things B1 is meant to
     * find out.
     *
     * Errs towards spending. Not knowing — the primary has not arrived yet, or
     * came from a client too old to measure — transcribes, because a wasted
     * upload is cheaper than a sentence nobody has.
     */
    private function satelliteIsNeeded(LiveMeetingSession $session, int $chunkNumber): bool
    {
        $primary = $session->chunks()
            ->where('chunk_number', $chunkNumber)
            ->where('role', ChunkRole::Primary)
            ->first();

        if ($primary === null || $primary->speech_dbfs === null) {
            return true;
        }

        if ($primary->status === ChunkStatus::Failed) {
            return true;
        }

        return $primary->speech_dbfs < self::FAINT_DBFS;
    }

    /**
     * @return array{session: LiveMeetingSession, chunks: \Illuminate\Database\Eloquent\Collection, extractions: \Illuminate\Database\Eloquent\Collection}
     */
    public function getSessionState(LiveMeetingSession $session, ?int $sinceChunk = null): array
    {
        $completedChunks = $session->chunks()
            ->where('status', ChunkStatus::Completed)
            ->when($sinceChunk !== null, fn ($query) => $query->where('chunk_number', '>', $sinceChunk))
            ->orderBy('chunk_number')
            ->get();

        $extractions = $session->meeting->extractions()
            ->latest()
            ->get();

        return [
            'session' => $session,
            'chunks' => $completedChunks,
            'extractions' => $extractions,
        ];
    }

    /**
     * What the client needs to pick up where it left off.
     *
     * A phone can be killed by the OS mid-meeting, and a websocket can miss
     * messages while the screen is off. On reconnect the app asks for this and
     * resends only the gaps, which is what keeps a merged transcript whole.
     *
     * @return array{next_chunk_number: int, missing_chunks: array<int, int>, stats: array<string, int>}
     */
    public function getResumeState(LiveMeetingSession $session, ?string $deviceId = null): array
    {
        $chunks = $session->chunks()->get(['chunk_number', 'status', 'device_id']);

        // Numbering is per device: a satellite that joined at minute ten is on
        // its own sequence, and answering it with the primary's next number
        // would have it upload over chunks it never recorded.
        //
        // A null device means "the whole sitting", which is what the browser
        // recorder asks and must keep being told.
        $mine = $deviceId === null
            ? $chunks
            : $chunks->where('device_id', $deviceId);

        $received = $mine->pluck('chunk_number')->all();
        $highest = $received === [] ? -1 : max($received);

        $missing = [];
        for ($number = 0; $number < $highest; $number++) {
            if (! in_array($number, $received, true)) {
                $missing[] = $number;
            }
        }

        return [
            'next_chunk_number' => $highest + 1,
            'missing_chunks' => $missing,
            'seconds_into_chunk' => $this->secondsIntoChunk($session),
            'stats' => [
                'chunks_total' => $chunks->count(),
                'chunks_completed' => $chunks->where('status', ChunkStatus::Completed)->count(),
                'chunks_pending' => $chunks->where('status', ChunkStatus::Pending)->count(),
                'chunks_processing' => $chunks->where('status', ChunkStatus::Processing)->count(),
                'chunks_failed' => $chunks->where('status', ChunkStatus::Failed)->count(),
            ],
        ];
    }

    /**
     * How far past the last chunk boundary the sitting currently is.
     *
     * A device joining as a satellite is told the next chunk number, which
     * says where the boundary is but not how long ago it passed. Without this
     * it would start cutting on its own clock and every one of its chunks
     * would straddle two of the primary's.
     *
     * Measured from when the last chunk was received rather than from the
     * session's start, so a sitting that was paused for ten minutes does not
     * report itself as ten minutes into a fifteen-second window. Accurate to
     * roughly the upload latency, a few hundred milliseconds — which is ample,
     * because this only has to agree on *which* window a chunk belongs to.
     * B1 never mixes samples, so nothing here needs sample accuracy.
     */
    private function secondsIntoChunk(LiveMeetingSession $session): float
    {
        $chunkSeconds = (float) ($session->config['chunk_interval'] ?? 30);

        $since = $session->chunks()->max('created_at');
        $from = $since === null
            ? $session->started_at
            : \Illuminate\Support\Carbon::parse($since);

        if ($from === null) {
            return 0.0;
        }

        $elapsed = (float) $from->diffInMilliseconds(now(), absolute: true) / 1000;

        // Longer than a whole chunk means the primary has stopped uploading —
        // it died, or the network went. Reported as nothing rather than as a
        // wrapped-around figure, so a satellite joining then simply starts at
        // the next boundary instead of aiming at a window that never came.
        return $elapsed >= $chunkSeconds ? 0.0 : round($elapsed, 3);
    }

    private function mergeChunksIntoTranscription(LiveMeetingSession $session): ?AudioTranscription
    {
        $completedChunks = $session->chunks()
            ->where('status', ChunkStatus::Completed)
            ->whereNotNull('text')
            ->orderBy('chunk_number')
            ->get();

        if ($completedChunks->isEmpty()) {
            return null;
        }

        $fullText = $completedChunks->pluck('text')->join("\n");

        // Skipped chunks are excluded on purpose. They are satellite audio we
        // chose not to transcribe because the primary heard that moment
        // perfectly well — nothing is missing from the transcript because of
        // them. Counting them here would fire LiveTranscriptIncomplete and
        // tell somebody their minutes have holes in them after every single
        // meeting that used a second microphone.
        $droppedChunks = $session->chunks()
            ->whereIn('status', [ChunkStatus::Pending, ChunkStatus::Processing, ChunkStatus::Failed])
            ->count();

        if ($droppedChunks > 0) {
            Log::warning('Live session transcript is incomplete; chunks were dropped from the merge.', [
                'session_id' => $session->id,
                'meeting_id' => $session->minutes_of_meeting_id,
                'merged_chunks' => $completedChunks->count(),
                'dropped_chunks' => $droppedChunks,
            ]);
        }

        $transcription = AudioTranscription::query()->create([
            'minutes_of_meeting_id' => $session->minutes_of_meeting_id,
            'uploaded_by' => $session->started_by,
            'original_filename' => "live_session_{$session->id}.webm",
            'file_path' => "live_session/{$session->id}",
            'mime_type' => 'audio/webm',
            'file_size' => 0,
            'duration_seconds' => $session->total_duration_seconds,
            'language' => 'en',
            'status' => TranscriptionStatus::Completed,
            'full_text' => $fullText,
            'provider_metadata' => [
                'merged_chunks' => $completedChunks->count(),
                'dropped_chunks' => $droppedChunks,
            ],
            'completed_at' => now(),
        ]);

        if ($droppedChunks > 0) {
            event(new LiveTranscriptIncomplete(
                session: $session,
                transcription: $transcription,
                mergedChunks: $completedChunks->count(),
                droppedChunks: $droppedChunks,
            ));
        }

        $this->writeSegments($transcription, $completedChunks);

        $session->meeting->inputs()->create([
            'type' => InputType::BrowserRecording,
            'source_type' => AudioTranscription::class,
            'source_id' => $transcription->id,
        ]);

        return $transcription;
    }

    /**
     * Turns the merged chunks into transcript segments.
     *
     * A chunk that carried its own segments contributes one per utterance,
     * shifted onto the meeting's timeline by the chunk's own start. A chunk
     * that carried none contributes a single segment spanning the whole
     * fifteen seconds, which is what every sitting produced before chunks
     * began keeping them and is what the browser recorder still produces.
     *
     * The coarse form is not merely a fallback for old data — it is the shape
     * that makes speaker attribution impossible, because one segment covering
     * fifteen seconds of a meeting cannot belong to one person. Both forms are
     * supported so a single sitting can mix them, which happens the moment
     * this ships and a session resumes across the deploy.
     *
     * @param  \Illuminate\Support\Collection<int, LiveTranscriptChunk>  $chunks
     */
    private function writeSegments(AudioTranscription $transcription, Collection $chunks): void
    {
        $rows = [];
        $now = now();

        foreach ($chunks as $chunk) {
            foreach ($this->segmentsOf($chunk) as $segment) {
                $rows[] = [
                    'audio_transcription_id' => $transcription->id,
                    'text' => $segment['text'],
                    'speaker' => $segment['speaker'],
                    'start_time' => $segment['start_time'],
                    'end_time' => $segment['end_time'],
                    'confidence' => $segment['confidence'],
                    'sequence_order' => count($rows),
                    'is_edited' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows === []) {
            return;
        }

        // One statement rather than a create() each: an hour-long sitting is
        // several hundred segments, and this runs inside the request that ends
        // the session while somebody waits on the Stop button.
        TranscriptionSegment::query()->insert($rows);
    }

    /**
     * One chunk's segments, on the meeting's timeline.
     *
     * Times come back from JSON as ints whenever they were whole numbers, so
     * they are cast rather than trusted — a segment at "0" that stays an int
     * reaches a float column and a float comparison as the wrong type.
     *
     * @return array<int, array<string, mixed>>
     */
    private function segmentsOf(LiveTranscriptChunk $chunk): array
    {
        $offset = (float) $chunk->start_time;
        $segments = $chunk->segments ?? [];

        if ($segments === []) {
            return [[
                'text' => $chunk->text,
                'speaker' => $chunk->speaker,
                'start_time' => $offset,
                'end_time' => (float) $chunk->end_time,
                'confidence' => $chunk->confidence,
            ]];
        }

        return array_map(static fn (array $segment): array => [
            'text' => $segment['text'],
            'speaker' => $segment['speaker'] ?? null,
            'start_time' => $offset + (float) $segment['start_time'],
            'end_time' => $offset + (float) $segment['end_time'],
            'confidence' => isset($segment['confidence']) ? (float) $segment['confidence'] : null,
        ], $segments);
    }
}
