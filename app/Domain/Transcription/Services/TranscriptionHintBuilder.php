<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Services;

use App\Domain\Meeting\Models\MinutesOfMeeting;

/**
 * Builds the recognition hints a transcription call can be given about a
 * meeting. Uploads and live chunks are transcribed by different models but
 * describe the same meeting, so both take their hints from here.
 */
class TranscriptionHintBuilder
{
    /**
     * Proper nouns the recording is likely to contain. Attendee and
     * organisation names are exactly what a general model mishears, and they
     * are the words a reader most notices getting wrong.
     *
     * @return array<int, string>
     */
    public function keywordsFor(?MinutesOfMeeting $meeting): array
    {
        if (! $meeting) {
            return [];
        }

        $attendees = $meeting->attendees()->get();

        return $attendees->pluck('name')
            ->merge($attendees->pluck('company'))
            ->push($meeting->title)
            ->filter()
            ->map(fn (string $value) => trim($value))
            ->reject(fn (string $value) => $value === '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Languages to expect. The meeting's own setting comes first, followed by
     * the configured fallbacks — meetings here routinely switch language
     * mid-sentence, so naming only one leaves the model guessing at the rest.
     *
     * @return array<int, string>
     */
    public function languagesFor(?MinutesOfMeeting $meeting): array
    {
        $hints = array_merge(
            array_filter([$meeting?->language]),
            (array) config('ai.transcription_language_hints', []),
        );

        return array_values(array_unique(array_filter($hints)));
    }
}
