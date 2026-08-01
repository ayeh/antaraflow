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
    /** Shorter fragments than this carry no recognition signal of their own. */
    private const MIN_NAME_PART_LENGTH = 3;

    /**
     * Connectors and honorifics that appear inside names without identifying
     * anyone, so they are not worth hinting on their own.
     *
     * @var array<int, string>
     */
    private const NAME_PARTICLES = [
        'bin', 'binti', 'bte', 'a/l', 'a/p',
        'dr', 'dr.', 'prof', 'ir', 'ts', 'sr',
        'datuk', 'dato', "dato'", 'datin', 'haji', 'hajah',
        'encik', 'puan', 'tuan', 'cik', 'kak', 'abang',
        'mr', 'mrs', 'ms', 'the', 'and', 'dan',
    ];

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

        $names = $attendees->pluck('name')
            ->filter()
            ->flatMap(fn (string $name) => $this->nameForms($name));

        // Organisations and meeting titles are spoken whole, so they are not
        // broken up — their individual words are ordinary vocabulary.
        return $names
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
     * A person's full name plus the parts of it worth hinting on their own.
     *
     * People are addressed by one part of their name far more often than by
     * all of it — a transcript of "Noor Ariff" being called "Ariff" came back
     * as "Arif" and "Arim", because the hint only ever named them in full.
     *
     * @return array<int, string>
     */
    private function nameForms(string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        if ($name === '') {
            return [];
        }

        $parts = array_filter(
            explode(' ', $name),
            fn (string $part) => mb_strlen($part) >= self::MIN_NAME_PART_LENGTH
                && ! in_array(mb_strtolower($part), self::NAME_PARTICLES, true),
        );

        return array_values(array_unique([$name, ...$parts]));
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
