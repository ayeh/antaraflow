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
     * Above this many words the type-token ratio stops meaning anything: it falls
     * with length for real speech too, because ordinary talk saturates its common
     * vocabulary. Applying MIN_DISTINCT_RATIO to a whole meeting therefore wiped
     * genuine minutes wholesale. A live chunk (~15s) never reaches this many
     * words, so the ratio test stays scoped below it; longer text is judged by
     * its absolute vocabulary instead.
     */
    private const SHORT_TEXT_TOKENS = 150;

    /**
     * Fewest distinct words a genuine long transcript ever holds. Real minutes
     * run to hundreds of distinct words within a couple of minutes of speech
     * (the smallest real recording measured kept 180+); a phrase looped for an
     * hour — "it's a beautiful day …", "tidak, okey, tidak, okey …" — never
     * leaves double digits however long it runs. That gap, not the ratio, is what
     * separates a loop from real speech once the text is long.
     */
    private const MIN_DISTINCT_VOCABULARY = 40;

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
        $distinctCount = count($frequencies);

        // One word swallowing the text is a loop at any length.
        if (max($frequencies) / $count >= self::DOMINANCE_THRESHOLD) {
            return true;
        }

        // Short text: the type-token ratio still tells a repeated phrase apart
        // from speech before length drags every transcript's ratio down.
        if ($count <= self::SHORT_TEXT_TOKENS) {
            return $distinctCount / $count <= self::MIN_DISTINCT_RATIO;
        }

        // Long text: a real meeting keeps introducing words; a loop is trapped
        // in a handful of them no matter how many times they repeat.
        return $distinctCount <= self::MIN_DISTINCT_VOCABULARY;
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
