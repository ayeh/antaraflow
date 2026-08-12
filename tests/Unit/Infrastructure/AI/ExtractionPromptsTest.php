<?php

declare(strict_types=1);

use App\Infrastructure\AI\Prompts\ExtractionPrompts;

describe('decisions', function () {
    // A one-way marketing video, recorded as a test, came back with four "Key
    // Decisions" attributed to the presenter. Nothing in the old prompt drew a
    // line between a thing the meeting settled and a thing somebody said.
    test('rules out the things that are not decisions', function () {
        $prompt = ExtractionPrompts::decisions('...');

        expect($prompt)
            ->toContain('proposal, motion or suggestion that was not carried')
            ->toContain('instruction, recommendation or piece of advice')
            ->toContain('opinion, preference')
            ->toContain('without the meeting agreeing to it');
    });

    test('says outright that returning nothing is a correct answer', function () {
        expect(ExtractionPrompts::decisions('...'))
            ->toContain('return an empty array')
            ->toContain('do not manufacture decisions');
    });

    // "Who made or proposed the decision" invited the name of whoever raised a
    // motion, carried or not, into the record as its author.
    test('does not let a proposer be recorded as the one who decided', function () {
        expect(ExtractionPrompts::decisions('...'))
            ->toContain('Do not put the name of someone who merely proposed it');
    });

    test('carries the transcript', function () {
        expect(ExtractionPrompts::decisions('Board resolved to adopt the budget.'))
            ->toContain('Board resolved to adopt the budget.');
    });

    // The framing is part of the fix: a model told it is an expert at finding
    // decisions treats finding none as a failure.
    test('the system message asks for conservatism, not expertise', function () {
        expect(ExtractionPrompts::decisionsSystemMessage())
            ->toContain('conservative')
            ->toContain('empty array')
            ->not->toContain('expert');
    });
});

describe('topics', function () {
    test('tells the model how long the recording actually is', function () {
        expect(ExtractionPrompts::topics('...', 146))
            ->toContain('2 minutes 26 seconds')
            ->toContain('cannot exceed it');
    });

    test('reads a whole number of minutes without a stray zero', function () {
        expect(ExtractionPrompts::topics('...', 600))->toContain('10 minutes long');
    });

    test('keeps seconds when that is all there is', function () {
        expect(ExtractionPrompts::topics('...', 42))->toContain('42 seconds');
    });

    // Without audio there is nothing to anchor an estimate to, and a guess
    // would be stored and displayed as though it were measured.
    test('asks for no duration at all when the length is unknown', function (?int $seconds) {
        $prompt = ExtractionPrompts::topics('...', $seconds);

        expect($prompt)
            ->toContain('Omit "duration_minutes" entirely')
            ->not->toContain('cannot exceed it');
    })->with([
        'no audio' => null,
        'zero length' => 0,
    ]);
});
