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
