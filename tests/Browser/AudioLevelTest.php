<?php

declare(strict_types=1);

it('converts linear amplitude to dBFS', function () {
    $page = visit('/__audio-harness');

    $page->assertScript('window.audioHarness.level.toDbfs(1)', 0);
    $page->assertScript('Math.round(window.audioHarness.level.toDbfs(0.5))', -6);
    $page->assertScript('window.audioHarness.level.toDbfs(0)', -100);

    // Amplitudes below the floor are clamped rather than running off to -Infinity.
    $page->assertScript('window.audioHarness.level.toDbfs(0.000001)', -100);
});

it('computes rms from a byte time-domain buffer', function () {
    $page = visit('/__audio-harness');

    // A buffer of all 128 is silence: Uint8 time domain is centred on 128.
    $page->assertScript('window.audioHarness.level.rmsFromTimeDomain(new Uint8Array(64).fill(128))', 0);

    // A constant half-scale offset reads as exactly 0.5, which pins both the
    // 128 centring and the 128 divisor: forget either and this moves.
    $page->assertScript('window.audioHarness.level.rmsFromTimeDomain(new Uint8Array(64).fill(192))', 0.5);

    // Full-scale square wave alternating between the extremes reads as 1.
    $page->assertScript(
        <<<'JS'
        (() => {
            const buffer = new Uint8Array(64);
            for (let i = 0; i < buffer.length; i++) {
                buffer[i] = i % 2 === 0 ? 0 : 255;
            }
            return Math.round(window.audioHarness.level.rmsFromTimeDomain(buffer));
        })()
        JS,
        1
    );

    // An empty buffer has no samples to average; it must not read as NaN.
    $page->assertScript('window.audioHarness.level.rmsFromTimeDomain(new Uint8Array(0))', 0);
});

it('reports clipping only at the very top of the range', function () {
    $page = visit('/__audio-harness');

    $page->assertScript('window.audioHarness.level.isClipping(-0.5)', true);
    $page->assertScript('window.audioHarness.level.isClipping(-6)', false);
});
