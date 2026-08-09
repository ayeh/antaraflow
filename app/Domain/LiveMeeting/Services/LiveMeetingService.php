<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Services;

use App\Domain\AI\Jobs\ExtractMeetingDataJob;
use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
use App\Domain\LiveMeeting\Events\LiveTranscriptIncomplete;
use App\Domain\LiveMeeting\Jobs\LiveTranscriptionJob;
use App\Domain\LiveMeeting\Models\LiveMeetingSession;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Models\TranscriptionSegment;
use App\Models\User;
use App\Support\Enums\InputType;
use App\Support\Enums\TranscriptionStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiveMeetingService
{
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

        $this->mergeChunksIntoTranscription($session);

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
    public function findExistingChunk(LiveMeetingSession $session, int $chunkNumber): ?LiveTranscriptChunk
    {
        return $session->chunks()->where('chunk_number', $chunkNumber)->first();
    }

    public function processChunk(
        LiveMeetingSession $session,
        UploadedFile $file,
        int $chunkNumber,
        float $startTime,
        float $endTime,
    ): LiveTranscriptChunk {
        $orgId = $session->meeting->organization_id;
        $path = "organizations/{$orgId}/audio/live/{$session->id}";
        $storedPath = $file->storeAs(
            $path,
            sprintf('chunk_%05d.%s', $chunkNumber, $file->getClientOriginalExtension()),
            'local',
        );

        $chunk = LiveTranscriptChunk::query()->create([
            'live_meeting_session_id' => $session->id,
            'chunk_number' => $chunkNumber,
            'audio_file_path' => $storedPath,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => ChunkStatus::Pending,
        ]);

        LiveTranscriptionJob::dispatch($chunk);

        return $chunk;
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
    public function getResumeState(LiveMeetingSession $session): array
    {
        $chunks = $session->chunks()->get(['chunk_number', 'status']);

        $received = $chunks->pluck('chunk_number')->all();
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
            'stats' => [
                'chunks_total' => $chunks->count(),
                'chunks_completed' => $chunks->where('status', ChunkStatus::Completed)->count(),
                'chunks_pending' => $chunks->where('status', ChunkStatus::Pending)->count(),
                'chunks_processing' => $chunks->where('status', ChunkStatus::Processing)->count(),
                'chunks_failed' => $chunks->where('status', ChunkStatus::Failed)->count(),
            ],
        ];
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

        $droppedChunks = $session->chunks()
            ->where('status', '!=', ChunkStatus::Completed)
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

        foreach ($completedChunks as $index => $chunk) {
            TranscriptionSegment::query()->create([
                'audio_transcription_id' => $transcription->id,
                'text' => $chunk->text,
                'speaker' => $chunk->speaker,
                'start_time' => $chunk->start_time,
                'end_time' => $chunk->end_time,
                'confidence' => $chunk->confidence,
                'sequence_order' => $index,
                'is_edited' => false,
            ]);
        }

        $session->meeting->inputs()->create([
            'type' => InputType::BrowserRecording,
            'source_type' => AudioTranscription::class,
            'source_id' => $transcription->id,
        ]);

        return $transcription;
    }
}
