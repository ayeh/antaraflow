<?php

declare(strict_types=1);

it('does not warn before the sustained window has elapsed', function () {
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const w = window.audioHarness.quietWarning.createQuietWarning();
            let fired = false;
            for (let t = 0; t < 14; t++) {
                fired = w.observe(-50, t * 1000) || fired;
            }
            return fired;
        })()
    JS, false);
});

it('warns once the level stays low for the full window', function () {
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const w = window.audioHarness.quietWarning.createQuietWarning();
            let fired = false;
            for (let t = 0; t <= 16; t++) {
                fired = w.observe(-50, t * 1000) || fired;
            }
            return fired;
        })()
    JS, true);
});

it('never warns twice', function () {
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const w = window.audioHarness.quietWarning.createQuietWarning();
            let count = 0;
            for (let t = 0; t <= 60; t++) {
                if (w.observe(-50, t * 1000)) { count++; }
            }
            return count;
        })()
    JS, 1);
});

it('warns at exactly the sustained window, not a tick either side', function () {
    // Pins the boundary: the loops elsewhere would still pass if the window
    // were off by one sample in either direction.
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const w = window.audioHarness.quietWarning.createQuietWarning();
            for (let t = 0; t <= 60; t++) {
                if (w.observe(-50, t * 1000)) { return t; }
            }
            return null;
        })()
    JS, 15);
});

it('can warn again for a fresh recording after a reset', function () {
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const w = window.audioHarness.quietWarning.createQuietWarning();
            for (let t = 0; t <= 20; t++) { w.observe(-50, t * 1000); }
            w.reset();
            let firedAgain = false;
            for (let t = 100; t <= 120; t++) {
                firedAgain = w.observe(-50, t * 1000) || firedAgain;
            }
            return firedAgain;
        })()
    JS, true);
});

it('resets the timer when the level recovers', function () {
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const w = window.audioHarness.quietWarning.createQuietWarning();
            let fired = false;
            for (let t = 0; t <= 14; t++) { w.observe(-50, t * 1000); }
            w.observe(-20, 15000);                       // someone spoke up
            for (let t = 16; t <= 25; t++) {             // under the window again
                fired = w.observe(-50, t * 1000) || fired;
            }
            return fired;
        })()
    JS, false);
});
