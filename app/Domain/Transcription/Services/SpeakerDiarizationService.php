<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Services;

use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Models\TranscriptionSegment;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use Illuminate\Support\Collection;

class SpeakerDiarizationService
{
    public function __construct(
        private AIProviderInterface $aiProvider,
    ) {}

    /**
     * Re-analyze speaker assignments using AI for a completed transcription.
     *
     * @return array<int, string> Map of segment_id => speaker_label
     */
    public function analyze(AudioTranscription $transcription): array
    {
        $segments = $transcription->segments()->orderBy('sequence_order')->get();
        $attendees = $transcription->minutesOfMeeting->attendees()->where('is_present', true)->get();

        if ($segments->isEmpty()) {
            return [];
        }

        $prompt = $this->buildDiarizationPrompt($segments, $attendees);

        $response = $this->aiProvider->chat($prompt, [
            'system' => 'You are a meeting transcription analyst specializing in speaker identification. Respond only with valid JSON.',
        ]);

        return $this->parseDiarizationResponse($response, $segments);
    }

    /**
     * Apply analyzed speaker labels to transcription segments.
     */
    public function applyLabels(AudioTranscription $transcription, array $speakerMap): int
    {
        $updated = 0;

        foreach ($speakerMap as $segmentId => $speakerLabel) {
            $affected = TranscriptionSegment::query()
                ->where('id', $segmentId)
                ->where('audio_transcription_id', $transcription->id)
                ->where('is_edited', false)
                ->update(['speaker' => $speakerLabel]);

            $updated += $affected;
        }

        return $updated;
    }

    /**
     * Full pipeline: analyze and apply in one call.
     */
    public function diarize(AudioTranscription $transcription): int
    {
        $speakerMap = $this->analyze($transcription);

        return $this->applyLabels($transcription, $speakerMap);
    }

    private function buildDiarizationPrompt(Collection $segments, Collection $attendees): string
    {
        $attendeeList = $attendees->map(fn (MomAttendee $a) => [
            'name' => $a->name,
            'role' => $a->role?->value ?? 'participant',
        ])->toArray();

        $segmentData = $segments->map(fn (TranscriptionSegment $s) => [
            'id' => $s->id,
            'seq' => $s->sequence_order,
            'text' => mb_substr($s->text, 0, 500),
            'start' => round($s->start_time, 1),
            'end' => round($s->end_time, 1),
            'current_speaker' => $s->speaker,
        ])->toArray();

        $attendeeJson = json_encode($attendeeList, JSON_UNESCAPED_UNICODE);
        $segmentJson = json_encode($segmentData, JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        Analyze this meeting transcript and identify who is speaking in each segment.

        ## Attendees present in this meeting:
        {$attendeeJson}

        ## Transcript segments (with current heuristic speaker labels):
        {$segmentJson}

        ## Instructions:
        1. Analyze conversation patterns: turn-taking, responses, name mentions, role references
        2. Try to match speakers to attendees when there are clear contextual clues
        3. If you cannot confidently match to an attendee, use "Speaker 1", "Speaker 2" etc. (consistent numbering)
        4. Segments from the same person should have the same label
        5. Consider: if someone says "Thank you Ahmad" the NEXT segment is likely NOT Ahmad

        ## Response format:
        Return a JSON object mapping segment ID to speaker label:
        {"<segment_id>": "<speaker_name_or_label>", ...}

        Only return the JSON object, nothing else.
        PROMPT;
    }

    /** @return array<int, string> */
    private function parseDiarizationResponse(string $response, Collection $segments): array
    {
        $validIds = $segments->pluck('id')->toArray();

        $jsonMatch = preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $response, $matches);
        if (! $jsonMatch) {
            return [];
        }

        $parsed = json_decode($matches[0], true);
        if (! is_array($parsed)) {
            return [];
        }

        $result = [];
        foreach ($parsed as $segmentId => $speaker) {
            $intId = (int) $segmentId;
            if (in_array($intId, $validIds, true) && is_string($speaker) && mb_strlen($speaker) <= 100) {
                $result[$intId] = trim($speaker);
            }
        }

        return $result;
    }
}
