<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Support;

use App\Domain\LiveMeeting\Enums\ChunkRole;
use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use Illuminate\Support\Collection;

/**
 * Chooses which device's transcript of a moment to keep.
 *
 * Two microphones in a room produce two transcripts of the same fifteen
 * seconds, and one of them is better. This picks it — and it picks between
 * finished transcripts rather than between waveforms, which is the whole
 * reason B1 needs no alignment, no crossfades, and no digital signal
 * processing at all.
 *
 * Pure by design: no audio, no network, no database. Everything it needs is
 * already on the rows, which is what makes the rule that decides a meeting's
 * transcript something that can be argued with in a unit test.
 */
class ChunkSelector
{
    /**
     * How much louder a chunk must be, in dB, to win on level despite scoring
     * lower on confidence.
     *
     * Transcription confidence is a shaky number — providers disagree about
     * what it means and it moves with the language as much as with the audio.
     * A large level difference is a physical fact and outranks it. A small one
     * does not, hence the margin.
     */
    private const LOUDER_BY = 8.0;

    /**
     * How far apart two confidence scores have to be before the difference is
     * worth acting on at all.
     */
    private const CLEARLY_BETTER = 0.05;

    /**
     * The best transcript of each moment, keyed by chunk number.
     *
     * @param  Collection<int, LiveTranscriptChunk>  $chunks
     * @return Collection<int, LiveTranscriptChunk>
     */
    public function bestOfEach(Collection $chunks): Collection
    {
        return $chunks
            ->filter(fn (LiveTranscriptChunk $chunk): bool => $this->isUsable($chunk))
            ->groupBy('chunk_number')
            // Sorted rather than left in arrival order, so the tie-break below
            // is a rule and not an accident of which upload landed first.
            ->map(fn (Collection $candidates): LiveTranscriptChunk => $this->best(
                $candidates->sortBy(self::primaryFirst(...))->values(),
            ))
            ->sortKeys()
            ->values();
    }

    /**
     * @param  Collection<int, LiveTranscriptChunk>  $candidates
     */
    private function best(Collection $candidates): LiveTranscriptChunk
    {
        return $candidates->reduce(
            function (?LiveTranscriptChunk $winner, LiveTranscriptChunk $candidate): LiveTranscriptChunk {
                if ($winner === null) {
                    return $candidate;
                }

                return $this->beats($candidate, $winner) ? $candidate : $winner;
            },
        );
    }

    /**
     * Whether [$candidate] is a better transcript of this moment than
     * [$winner].
     *
     * Ties go to the incumbent, and the primary is always the incumbent
     * because it is ordered first. A sitting whose two devices heard equally
     * well should read exactly as it would have with one device.
     */
    private function beats(LiveTranscriptChunk $candidate, LiveTranscriptChunk $winner): bool
    {
        $louder = $this->levelGap($candidate, $winner);

        if ($louder !== null && $louder >= self::LOUDER_BY) {
            return true;
        }

        if ($louder !== null && $louder <= -self::LOUDER_BY) {
            return false;
        }

        $gap = ($candidate->confidence ?? 0.0) - ($winner->confidence ?? 0.0);

        return $gap > self::CLEARLY_BETTER;
    }

    /**
     * How much louder the candidate's speech was, or null when either side
     * did not measure and there is nothing to compare.
     */
    private function levelGap(LiveTranscriptChunk $candidate, LiveTranscriptChunk $winner): ?float
    {
        if ($candidate->speech_dbfs === null || $winner->speech_dbfs === null) {
            return null;
        }

        return (float) $candidate->speech_dbfs - (float) $winner->speech_dbfs;
    }

    /**
     * A chunk with no words in it is not a candidate, whatever its level.
     */
    private function isUsable(LiveTranscriptChunk $chunk): bool
    {
        return $chunk->status === ChunkStatus::Completed
            && $chunk->text !== null
            && $chunk->text !== '';
    }

    /**
     * Orders candidates so the primary is the one a tie falls to.
     */
    public static function primaryFirst(LiveTranscriptChunk $chunk): int
    {
        return $chunk->role === ChunkRole::Primary ? 0 : 1;
    }
}
