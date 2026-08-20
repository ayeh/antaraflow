<?php

declare(strict_types=1);

it('is not stalled before it has ever been armed', function () {
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const w = window.audioHarness.chunkWatchdog.createChunkWatchdog();
            // Never reset or flushed: an unarmed watchdog must never ask to restart.
            return w.isStalled(10_000_000);
        })()
    JS, false);
});

it('is not stalled while chunks keep flushing within the limit', function () {
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const w = window.audioHarness.chunkWatchdog.createChunkWatchdog();
            w.reset(0);
            let stalled = false;
            // A flush every 30s for ten minutes stays healthy.
            for (let t = 30_000; t <= 600_000; t += 30_000) {
                stalled = w.isStalled(t) || stalled;
                w.flushed(t);
            }
            return stalled;
        })()
    JS, false);
});

it('reports a stall once two chunk lengths pass with no flush', function () {
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const w = window.audioHarness.chunkWatchdog.createChunkWatchdog();
            w.reset(0);
            // 61s with no flush is past the 60s limit.
            return w.isStalled(61_000);
        })()
    JS, true);
});

it('does not report a stall exactly at the limit', function () {
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const w = window.audioHarness.chunkWatchdog.createChunkWatchdog();
            w.reset(0);
            return w.isStalled(60_000);
        })()
    JS, false);
});

it('clears a stall once a fresh chunk flushes', function () {
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const w = window.audioHarness.chunkWatchdog.createChunkWatchdog();
            w.reset(0);
            const wasStalled = w.isStalled(90_000);
            w.flushed(90_000);
            const stillStalled = w.isStalled(100_000);
            return wasStalled && !stillStalled;
        })()
    JS, true);
});
