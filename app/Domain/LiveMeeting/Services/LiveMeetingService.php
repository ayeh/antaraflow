<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Services;

use App\Domain\AI\Jobs\ExtractMeetingDataJob;
use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Enums\LiveSessionStatus;
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

class LiveMeetingService
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function startSession(MinutesOfMeeting $meeting, User $user, array $config = []): LiveMeetingSession
    {
        $existingActiveSession = LiveMeetingSession::query()
            ->where('minutes_of_meeting_id', $meeting->id)
            ->where('status', LiveSessionStatus::Active)
            ->exists();

        if ($existingActiveSession) {
            throw new \RuntimeException('Meeting already has an active live session.');
        }

        $defaultConfig = ['chunk_interval' => 30, 'extraction_interval' => 300];

        return LiveMeetingSession::query()->create([
            'minutes_of_meeting_id' => $meeting->id,
            'started_by' => $user->id,
            'status' => LiveSessionStatus::Active,
            'config' => array_merge($defaultConfig, $config),
            'started_at' => now(),
        ]);
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
    public function getSessionState(LiveMeetingSession $session): array
    {
        $completedChunks = $session->chunks()
            ->where('status', ChunkStatus::Completed)
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
            'completed_at' => now(),
        ]);

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
