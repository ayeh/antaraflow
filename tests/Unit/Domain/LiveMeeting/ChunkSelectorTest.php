<?php

declare(strict_types=1);

use App\Domain\LiveMeeting\Enums\ChunkRole;
use App\Domain\LiveMeeting\Enums\ChunkStatus;
use App\Domain\LiveMeeting\Models\LiveTranscriptChunk;
use App\Domain\LiveMeeting\Support\ChunkSelector;
use Illuminate\Support\Collection;

/**
 * A candidate transcript, unsaved. Selection touches no database, so neither
 * does this file.
 */
function candidate(
    int $number,
    string $text,
    ChunkRole $role = ChunkRole::Primary,
    ?float $confidence = 0.9,
    ?float $speech = null,
    ChunkStatus $status = ChunkStatus::Completed,
): LiveTranscriptChunk {
    return new LiveTranscriptChunk([
        'chunk_number' => $number,
        'text' => $text,
        'role' => $role,
        'confidence' => $confidence,
        'speech_dbfs' => $speech,
        'status' => $status,
    ]);
}

/** @param array<int, LiveTranscriptChunk> $chunks */
function pick(array $chunks): Collection
{
    return (new ChunkSelector)->bestOfEach(new Collection($chunks));
}

it('keeps the only transcript of a moment', function () {
    $picked = pick([candidate(0, 'the chair opened the meeting')]);

    expect($picked)->toHaveCount(1)
        ->and($picked->first()->text)->toBe('the chair opened the meeting');
});

it('prefers the transcript the model was more sure of', function () {
    $picked = pick([
        candidate(0, 'from the primary', confidence: 0.55),
        candidate(0, 'from the satellite', ChunkRole::Satellite, confidence: 0.91),
    ]);

    expect($picked->first()->text)->toBe('from the satellite');
});

// The case the whole feature exists for: the person spoke at the far end of
// the table, the phone next to them heard it, and the laptop did not.
it('prefers the much louder transcript even when it scored lower', function () {
    $picked = pick([
        candidate(0, 'from the primary', confidence: 0.80, speech: -58.0),
        candidate(0, 'from the satellite', ChunkRole::Satellite, confidence: 0.70, speech: -34.0),
    ]);

    expect($picked->first()->text)->toBe('from the satellite');
});

// Confidence is a shaky number and a small level difference is not a physical
// fact worth overriding it with.
it('does not let a slightly louder transcript overrule a much surer one', function () {
    $picked = pick([
        candidate(0, 'from the primary', confidence: 0.92, speech: -40.0),
        candidate(0, 'from the satellite', ChunkRole::Satellite, confidence: 0.50, speech: -36.0),
    ]);

    expect($picked->first()->text)->toBe('from the primary');
});

it('never picks a chunk that was skipped, failed, or is still pending', function () {
    $picked = pick([
        candidate(0, 'from the primary', confidence: 0.40),
        candidate(0, 'skipped', ChunkRole::Satellite, confidence: 0.99, status: ChunkStatus::Skipped),
        candidate(0, 'failed', ChunkRole::Satellite, confidence: 0.99, status: ChunkStatus::Failed),
        candidate(0, 'pending', ChunkRole::Satellite, confidence: 0.99, status: ChunkStatus::Pending),
    ]);

    expect($picked)->toHaveCount(1)
        ->and($picked->first()->text)->toBe('from the primary');
});

it('ignores a transcript with no words in it', function () {
    $picked = pick([
        candidate(0, 'from the primary', confidence: 0.30),
        candidate(0, '', ChunkRole::Satellite, confidence: 0.99),
    ]);

    expect($picked->first()->text)->toBe('from the primary');
});

// A sitting whose two devices heard equally well must read exactly as it would
// have with one device.
it('leaves a tie with the device that was already winning', function () {
    $picked = pick([
        candidate(0, 'from the primary', confidence: 0.90, speech: -35.0),
        candidate(0, 'from the satellite', ChunkRole::Satellite, confidence: 0.90, speech: -35.0),
    ]);

    expect($picked->first()->text)->toBe('from the primary');
});

it('falls back to confidence when only one device measured its level', function () {
    $picked = pick([
        candidate(0, 'from the primary', confidence: 0.40, speech: null),
        candidate(0, 'from the satellite', ChunkRole::Satellite, confidence: 0.95, speech: -30.0),
    ]);

    expect($picked->first()->text)->toBe('from the satellite');
});

it('returns the moments in order, one for each', function () {
    $picked = pick([
        candidate(2, 'third'),
        candidate(0, 'first'),
        candidate(1, 'second', ChunkRole::Satellite),
        candidate(1, 'second, worse', confidence: 0.1),
    ]);

    expect($picked->pluck('text')->all())->toBe(['first', 'second', 'third']);
});

it('has nothing to say about a sitting that recorded nothing', function () {
    expect(pick([]))->toBeEmpty();
});

// The same tie, with the satellite's upload landing first. The rule has to be
// the role, not the arrival order.
it('leaves a tie with the primary whichever device uploaded first', function () {
    $picked = pick([
        candidate(0, 'from the satellite', ChunkRole::Satellite, confidence: 0.90, speech: -35.0),
        candidate(0, 'from the primary', confidence: 0.90, speech: -35.0),
    ]);

    expect($picked->first()->text)->toBe('from the primary');
});
