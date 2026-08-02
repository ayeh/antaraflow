<?php

declare(strict_types=1);

it('keeps samples in insertion order until capacity', function () {
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const t = window.audioHarness.tapeBuffer.createTape(4);
            t.push(1); t.push(2); t.push(3);
            return t.toArray().join(',');
        })()
    JS, '1,2,3');
});

it('drops the oldest sample once full', function () {
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const t = window.audioHarness.tapeBuffer.createTape(3);
            [1, 2, 3, 4, 5].forEach((v) => t.push(v));
            return t.toArray().join(',');
        })()
    JS, '3,4,5');
});

it('never exceeds capacity no matter how much is pushed', function () {
    // Asserts the contents, not just the length: a buffer that kept 60 stale
    // entries would satisfy a length-only check.
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const t = window.audioHarness.tapeBuffer.createTape(60);
            for (let i = 0; i < 100000; i++) { t.push(i); }
            const out = t.toArray();
            return [out.length, out[0], out[out.length - 1]].join(':');
        })()
    JS, '60:99940:99999');
});

it('is empty again after being cleared', function () {
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const t = window.audioHarness.tapeBuffer.createTape(3);
            [1, 2, 3, 4].forEach((v) => t.push(v));
            t.clear();
            if (t.toArray().length !== 0) { return 'not empty'; }
            t.push(9); t.push(8);
            return t.toArray().join(',');
        })()
    JS, '9,8');
});
