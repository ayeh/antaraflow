<?php

declare(strict_types=1);

use App\Domain\Transcription\Services\RepetitionGuard;

beforeEach(function () {
    $this->guard = new RepetitionGuard;
});

test('flags a single word looped for the whole chunk', function () {
    $text = trim(str_repeat('tidak, ', 200));

    expect($this->guard->isDegenerate($text))->toBeTrue();
});

test('flags a short phrase repeated until the vocabulary collapses', function () {
    $text = trim(str_repeat('okey lah ', 30));

    expect($this->guard->isDegenerate($text))->toBeTrue();
});

test('leaves ordinary meeting speech alone', function () {
    $text = 'Okay so we agree the budget goes to the recruitment portal first and marketing signs off next week.';

    expect($this->guard->isDegenerate($text))->toBeFalse();
});

test('leaves a short genuine affirmation alone', function () {
    expect($this->guard->isDegenerate('no, no, that is fine'))->toBeFalse();
});

test('does not flag emphatic but varied speech', function () {
    $text = 'No no no we cannot do that, it breaks the whole timeline and the client already signed.';

    expect($this->guard->isDegenerate($text))->toBeFalse();
});

test('treats an empty string as not degenerate', function () {
    expect($this->guard->isDegenerate(''))->toBeFalse();
});

test('leaves a long real transcript alone despite its low distinct-word ratio', function () {
    // A whole meeting saturates its common vocabulary, so its type-token ratio
    // sits far below the short-chunk threshold without any looping. The guard
    // must not mistake that length effect for a decode loop and wipe the minutes.
    $sentences = [
        'The committee agreed the procurement portal launches next quarter once finance signs off on the revised budget.',
        'Marketing will confirm the campaign schedule after legal reviews the vendor contract and the compliance checklist.',
        'We discussed staffing for the recruitment drive and decided the two new coordinators start after the annual audit closes.',
        'Operations raised concerns about the warehouse lease renewal, so procurement negotiates fresh terms with the landlord next week.',
        'Everyone accepted the updated delivery timeline, though the client still wants the pilot shipped before the holiday freeze.',
        'Engineering flagged that the migration script needs another rehearsal against production data before the maintenance window opens.',
        'Support reported a spike in tickets about billing invoices, which finance traces to the currency rounding change.',
        'The board asked for a summary deck covering hiring, revenue, churn, and the roadmap ahead of the investor call.',
    ];
    $text = trim(str_repeat(implode(' ', $sentences).' ', 20));

    expect(str_word_count($text))->toBeGreaterThan(2000)
        ->and($this->guard->isDegenerate($text))->toBeFalse();
});

test('still flags a single word looped across a long transcript', function () {
    $text = trim(str_repeat('tidak ', 2000));

    expect($this->guard->isDegenerate($text))->toBeTrue();
});

test('flags a short phrase looped across a long transcript', function () {
    // Balanced across a handful of words, this never trips the single-word
    // dominance test, and length puts it past the ratio test — but its whole
    // vocabulary is five words, which real speech never is at this length.
    $text = trim(str_repeat("it's a beautiful day ", 200));

    expect($this->guard->isDegenerate($text))->toBeTrue();
});

test('flags a two-word loop running the length of a transcript', function () {
    $text = trim(str_repeat('tidak okey ', 300));

    expect($this->guard->isDegenerate($text))->toBeTrue();
});
