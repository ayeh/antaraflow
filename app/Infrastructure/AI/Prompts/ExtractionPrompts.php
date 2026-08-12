<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Prompts;

/**
 * Prompts that every provider shares.
 *
 * These were copy-pasted into each provider, which meant four places to fix
 * and four chances for them to drift apart. The wording is part of the
 * product's correctness, not an implementation detail of any one vendor.
 */
final class ExtractionPrompts
{
    /**
     * What the model is told it is doing when extracting decisions.
     *
     * Deliberately not "an expert at identifying decisions": that framing makes
     * finding decisions the measure of a good answer, and a model measured that
     * way will find some in a transcript that contains none.
     */
    public static function decisionsSystemMessage(): string
    {
        return 'You record decisions for a formal minute book. You are conservative: '
            .'you record only what a meeting actually resolved, and you return an empty '
            .'array when it resolved nothing. Always respond with valid JSON.';
    }

    /**
     * A decision is a thing the meeting settled — not a thing somebody said.
     *
     * The distinction matters more here than it looks. Minutes are relied on as
     * the record of what a board committed to, so a suggestion written down as
     * a decision is not an untidy summary, it is a false entry in the record.
     */
    public static function decisions(string $transcript): string
    {
        return "Extract only the decisions that the meeting actually reached.\n\n"
            ."A decision is something the meeting resolved, agreed, approved or settled.\n\n"
            ."The following are NOT decisions, and must not be returned:\n"
            ."- a proposal, motion or suggestion that was not carried\n"
            ."- an instruction, recommendation or piece of advice given to others\n"
            ."- an opinion, preference, or a statement of what someone intends to do\n"
            ."- anything one speaker asserted without the meeting agreeing to it\n\n"
            ."Return a JSON array where each item has:\n"
            ."- \"decision\": what the meeting resolved\n"
            ."- \"context\": background for the decision (optional)\n"
            .'- "made_by": who the record attributes the decision to, only where the '
            .'transcript says so explicitly. Do not put the name of someone who merely '
            ."proposed it.\n\n"
            .'If the meeting reached no decisions, return an empty array. That is a correct '
            ."answer and an expected one — do not manufacture decisions to fill the list.\n\n"
            ."Respond with ONLY a valid JSON array, no other text.\n\n"
            ."Transcript:\n{$transcript}";
    }

    /**
     * Topics, with the length of the recording supplied.
     *
     * Without [$recordedSeconds] the model is asked to estimate a duration from
     * text alone, which it cannot do — so it returns what a meeting agenda
     * usually looks like. A 2m26s recording came back as five topics totalling
     * eighteen minutes.
     */
    public static function topics(string $transcript, ?int $recordedSeconds = null): string
    {
        $duration = $recordedSeconds !== null && $recordedSeconds > 0
            ? 'The recording is '.self::humanise($recordedSeconds).' long in total. Every '
                .'duration you return must be consistent with that: they are parts of this '
                .'recording, and together they cannot exceed it. Use whole minutes, and 0 '
                ."for a topic shorter than a minute.\n\n"
            : 'Omit "duration_minutes" entirely — the length of the recording is not '
                ."known, and a guess would be recorded as fact.\n\n";

        return "Identify the main topics discussed in the following meeting transcript.\n\n"
            .$duration
            ."Return a JSON array where each item has:\n"
            ."- \"title\": Topic title\n"
            ."- \"description\": Brief description of what was discussed\n"
            ."- \"duration_minutes\": How long this topic took, as an integer\n\n"
            ."Respond with ONLY a valid JSON array, no other text.\n\n"
            ."Transcript:\n{$transcript}";
    }

    private static function humanise(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $remainder = $seconds % 60;

        if ($minutes === 0) {
            return "{$seconds} seconds";
        }

        return $remainder === 0
            ? "{$minutes} minutes"
            : "{$minutes} minutes {$remainder} seconds";
    }
}
