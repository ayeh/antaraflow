<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Services;

/**
 * Recognises the decode loops speech-to-text models fall into on low-information
 * audio — silence, room noise, or a single word amplified into the speech band.
 *
 * Given such a chunk the model gets stuck and emits one token over and over
 * ("tidak, tidak, tidak, …"), and because each live chunk is handed the tail of
 * the previous one as context, one loop primes the next and the whole sitting
 * fills with a single word. This tells that garbage apart from real speech so it
 * can be dropped before it is stored or fed forward.
 */
class RepetitionGuard
{
    /**
     * A run of one word shorter than this is plausibly real — "no, no, no" said
     * to a question — so it is left alone. A loop runs for the whole chunk and
     * clears this many times over.
     */
    private const MIN_TOKENS = 8;

    /**
     * Share of all words a single token may take before the text reads as a
     * loop. Real speech, even emphatic, spreads itself across a vocabulary; it
     * does not spend most of a chunk on one word.
     */
    private const DOMINANCE_THRESHOLD = 0.6;

    /**
     * Fewest distinct words, as a share of the whole, that ordinary speech keeps.
     * A short phrase repeated ("okey lah okey lah …") never trips the single-word
     * dominance test but collapses the vocabulary just as badly.
     */
    private const MIN_DISTINCT_RATIO = 0.3;

    /**
     * Whether a transcript is a decode loop rather than speech.
     */
    public function isDegenerate(string $text): bool
    {
        $tokens = $this->tokenize($text);
        $count = count($tokens);

        if ($count < self::MIN_TOKENS) {
            return false;
        }

        $frequencies = array_count_values($tokens);

        $dominance = max($frequencies) / $count;
        $distinctRatio = count($frequencies) / $count;

        return $dominance >= self::DOMINANCE_THRESHOLD
            || $distinctRatio <= self::MIN_DISTINCT_RATIO;
    }

    /**
     * Words, lowercased and stripped of punctuation, so that "tidak," and
     * "tidak" count as the same token.
     *
     * @return array<int, string>
     */
    private function tokenize(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);

        return $words ?: [];
    }
}
