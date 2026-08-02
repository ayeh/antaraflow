<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The recorder lives on step 3 of the wizard. Opening the page anywhere else
 * leaves it behind a `display: none` ancestor, where every visibility and
 * computed-style assertion would pass for the wrong reason.
 */
function motionPage(): string
{
    return route('meetings.show', test()->meeting).'?step=3';
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

/**
 * Drive the meter by hand with an analyser of a known level.
 *
 * requestAnimationFrame is neutered so the test owns every frame; a real loop
 * running alongside would paint between assertions.
 */
function withRecorderMotion(string $body): string
{
    return <<<JS
    async () => {
        const recorder = window.Alpine.\$data(document.querySelector('[x-data^="audioRecorder"]'));
        const canvas = document.querySelector('[x-data^="audioRecorder"] canvas');
        recorder.canvasContext = canvas.getContext('2d');

        const realRequestAnimationFrame = window.requestAnimationFrame;
        window.requestAnimationFrame = () => 1;

        const stubAnalyser = (amplitude) => {
            recorder.analyserNode = {
                fftSize: 2048,
                getByteTimeDomainData: (buffer) => {
                    for (let i = 0; i < buffer.length; i++) {
                        buffer[i] = 128 + (i % 2 === 0 ? amplitude : -amplitude);
                    }
                },
            };
        };

        const drawFrames = async (n) => {
            for (let i = 0; i < n; i++) {
                recorder.drawWaveform();
                await new Promise((resolve) => setTimeout(resolve, 20));
            }
        };

        /** How many pixels of the canvas are neither background nor the grey idle line. */
        const litPixels = () => {
            const pixels = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;
            let lit = 0;
            for (let i = 0; i < pixels.length; i += 4) {
                const key = pixels[i] + ',' + pixels[i + 1] + ',' + pixels[i + 2];
                if (key === '13,115,119' || key === '217,119,6' || key === '220,38,38') {
                    lit++;
                }
            }
            return lit;
        };

        try {
            {$body}
        } finally {
            window.requestAnimationFrame = realRequestAnimationFrame;
            recorder.state = 'idle';
        }
    }
    JS;
}

it('has no accessibility issues on the recorder', function () {
    $this->actingAs($this->user);

    /**
     * Scoped to the recorder with axe's own context argument rather than
     * assertNoAccessibilityIssues(), which audits the whole document: the app
     * shell around this panel — the sidebar, the FAB, the command palette, the
     * wizard stepper — carries four pre-existing violations that predate this
     * branch, and a page-wide assertion here would report them as ours and
     * then be silenced. Those belong in their own change.
     */
    $page = visit(motionPage());

    $violations = $page->script(<<<'JS'
    async () => {
        const results = await window.axe.run(document.querySelector('[x-data^="audioRecorder"]'));

        return results.violations.map((v) => v.impact + ': ' + v.id + ' on ' + v.nodes.length + ' node(s)');
    }
    JS);

    expect($violations)->toBe([]);

    $page->assertNoJavaScriptErrors();
});

it('breathes on the button and nowhere near the tape', function () {
    $this->actingAs($this->user);

    /**
     * The one knowingly accepted violation of "motion means recording". It is
     * allowed because the button is a call to action while the tape is the
     * status display — so the breathing has to be on the button, at low
     * amplitude, and the tape has to stay off screen entirely while idle.
     */
    $page = visit(motionPage());

    $result = $page->script(<<<'JS'
    () => {
        const button = document.querySelector('#recorder-record');
        const style = getComputedStyle(button);
        const canvas = document.querySelector('[x-data^="audioRecorder"] canvas');

        return {
            animation: style.animationName,
            duration: style.animationDuration,
            iteration: style.animationIterationCount,
            canvasVisible: canvas.offsetParent !== null,
            animatedElsewhere: [...document.querySelectorAll('[x-data^="audioRecorder"] *')]
                .filter((el) => getComputedStyle(el).animationName === '_rec-breathe')
                .length,
        };
    }
    JS);

    expect($result['animation'])->toBe('_rec-breathe')
        ->and($result['duration'])->toBe('2.5s')
        ->and($result['iteration'])->toBe('infinite')
        // The meter is not merely still while idle — it is not on screen at all.
        ->and($result['canvasVisible'])->toBeFalse()
        ->and($result['animatedElsewhere'])->toBe(1);

    $page->assertNoJavaScriptErrors();
});

it('walks the countdown digit from teal through amber to crimson', function () {
    $this->actingAs($this->user);

    $page = visit(motionPage());

    /**
     * The colour and the pop say the same thing the number says: time is nearly
     * up. Read back off the rendered element, because a digit that is styled
     * but never displayed communicates nothing.
     */
    $result = $page->script(<<<'JS'
    async () => {
        const recorder = window.Alpine.$data(document.querySelector('[x-data^="audioRecorder"]'));
        recorder.state = 'countdown';

        const seen = [];
        for (const value of [3, 2, 1]) {
            recorder.countdownValue = value;
            await new Promise((resolve) => setTimeout(resolve, 60));

            const digit = document.querySelector('#recorder-countdown-digit');
            const style = digit ? getComputedStyle(digit) : null;

            seen.push({
                text: digit ? digit.textContent.trim() : null,
                visible: !!digit && digit.offsetParent !== null,
                colour: style ? style.color : null,
                animation: style ? style.animationName : null,
            });
        }

        recorder.state = 'idle';

        return seen;
    }
    JS);

    expect($result[0])->toBe([
        'text' => '3', 'visible' => true, 'colour' => 'rgb(13, 115, 119)', 'animation' => '_rec-pop',
    ])->and($result[1])->toBe([
        'text' => '2', 'visible' => true, 'colour' => 'rgb(217, 119, 6)', 'animation' => '_rec-pop',
    ])->and($result[2])->toBe([
        'text' => '1', 'visible' => true, 'colour' => 'rgb(220, 38, 38)', 'animation' => '_rec-pop',
    ]);

    $page->assertNoJavaScriptErrors();
});

it('morphs the record icon from circle to rounded square when it commits', function () {
    $this->actingAs($this->user);

    $result = visit(motionPage())->script(<<<'JS'
    async () => {
        const recorder = window.Alpine.$data(document.querySelector('[x-data^="audioRecorder"]'));
        const icon = () => document.querySelector('#recorder-record ._rec-morph');

        const before = getComputedStyle(icon()).borderRadius;

        recorder.state = 'countdown';

        // Past the end of the .35s transition: mid-flight the value is whatever
        // the easing happens to be passing through.
        await new Promise((resolve) => setTimeout(resolve, 450));
        const during = getComputedStyle(icon()).borderRadius;
        const label = document.querySelector('#recorder-record').textContent.trim();

        recorder.state = 'idle';

        return { before, during, label, transition: getComputedStyle(icon()).transitionDuration };
    }
    JS);

    expect($result['before'])->toBe('50%')
        ->and($result['during'])->toBe('28%')
        ->and($result['label'])->toBe('Starting...')
        ->and($result['transition'])->toContain('0.35s');
});

it('wakes the tape when recording starts and collapses it when recording stops', function () {
    $this->actingAs($this->user);

    /**
     * Both sweeps are painted on the canvas, so both are read back off pixels.
     * The wake animates the baseline only: a tape that has just been cleared
     * holds no levels, and sweeping invented ones up from flat would put a
     * shape on screen that no microphone ever produced.
     */
    $result = visit(motionPage())->script(withRecorderMotion(<<<'JS'
        stubAnalyser(64);
        recorder.resetMeter();
        recorder.state = 'recording';

        recorder.startSweep('in');
        recorder.drawWaveform();
        const wakeEarly = litPixels();
        await new Promise((resolve) => setTimeout(resolve, 200));
        recorder.drawWaveform();
        const wakeLate = litPixels();

        // Fill the tape with real level, then stop.
        recorder.resetMeter();
        await drawFrames(40);
        const beforeStop = litPixels();

        recorder.state = 'stopping';
        recorder.startSweep('out');
        recorder.drawWaveform();
        const collapseEarly = litPixels();

        await new Promise((resolve) => setTimeout(resolve, 250));
        recorder.drawWaveform();
        const collapseLate = litPixels();

        await new Promise((resolve) => setTimeout(resolve, 250));
        recorder.drawWaveform();
        const afterSweep = litPixels();

        return { wakeEarly, wakeLate, beforeStop, collapseEarly, collapseLate, afterSweep };
    JS));

    // The live baseline sweeps in from the left, so more of it is lit as it goes.
    expect($result['wakeLate'])->toBeGreaterThan($result['wakeEarly'])
        // The collapse eats the tape from the newest end, and by the time the
        // sweep is over there is nothing but the flat line left.
        ->and($result['collapseEarly'])->toBeGreaterThan(0)
        ->and($result['collapseLate'])->toBeLessThan($result['collapseEarly'])
        ->and($result['afterSweep'])->toBe(0)
        ->and($result['beforeStop'])->toBeGreaterThan(0);
});

it('shimmers only while there is work in progress to report', function () {
    $this->actingAs($this->user);

    $result = visit(motionPage())->script(withRecorderMotion(<<<'JS'
        recorder.resetMeter();

        const framesIn = async (state) => {
            recorder.state = state;
            const frames = [];
            for (let i = 0; i < 5; i++) {
                recorder.drawWaveform();
                frames.push(canvas.toDataURL());
                await new Promise((resolve) => setTimeout(resolve, 60));
            }
            return new Set(frames).size;
        };

        return {
            processing: await framesIn('processing'),
            uploading: await framesIn('uploading'),
            ready: await framesIn('ready'),
        };
    JS));

    expect($result['processing'])->toBeGreaterThan(1)
        ->and($result['uploading'])->toBeGreaterThan(1)
        // Nothing is happening in 'ready', so nothing moves.
        ->and($result['ready'])->toBe(1);
});

it('keeps the level meter alive when the user has asked for reduced motion', function () {
    $this->actingAs($this->user);

    /**
     * Pest can drive the preference: Playwright's `reducedMotion` context
     * option is passed straight through by visit()'s options array.
     *
     * The distinction that matters is that the meter is data, not decoration.
     * Under reduced motion it must keep measuring, keep pushing to the tape and
     * keep painting; what it loses is its attack/release easing, its peak
     * decay, and the wake, collapse and shimmer sweeps.
     */
    $result = visit(motionPage(), ['reducedMotion' => 'reduce'])->script(withRecorderMotion(<<<'JS'
        const mediaMatches = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Read while the recorder is still idle: the button is unmounted the
        // moment recording starts.
        const breathing = getComputedStyle(document.querySelector('#recorder-record')).animationName;

        recorder.resetMeter();
        recorder.state = 'recording';

        let amplitude = 4;
        recorder.analyserNode = {
            fftSize: 2048,
            getByteTimeDomainData: (buffer) => {
                amplitude = amplitude === 4 ? 96 : 4;
                for (let i = 0; i < buffer.length; i++) {
                    buffer[i] = 128 + (i % 2 === 0 ? amplitude : -amplitude);
                }
            },
        };

        const frames = [];
        for (let i = 0; i < 6; i++) {
            recorder.drawWaveform();
            frames.push(canvas.toDataURL());
            await new Promise((resolve) => setTimeout(resolve, 25));
        }

        const audioLevel = recorder.audioLevel;
        const tape = recorder._tape.toArray().length;

        // The sweeps refuse to start at all rather than running invisibly.
        recorder.startSweep('in');
        const sweep = recorder.currentSweep(performance.now());

        // The shimmer keeps its line but loses the travelling band.
        recorder.state = 'processing';
        const shimmer = [];
        for (let i = 0; i < 4; i++) {
            recorder.drawWaveform();
            shimmer.push(canvas.toDataURL());
            await new Promise((resolve) => setTimeout(resolve, 60));
        }

        return {
            mediaMatches,
            meterFrames: new Set(frames).size,
            tape,
            audioLevel,
            sweep,
            shimmerFrames: new Set(shimmer).size,
            breathing,
        };
    JS));

    expect($result['mediaMatches'])->toBeTrue()
        // Data: still measured, still stored, still drawn, frame by frame.
        ->and($result['meterFrames'])->toBeGreaterThan(1)
        ->and($result['tape'])->toBe(6)
        ->and($result['audioLevel'])->toBeGreaterThan(0)
        // Decoration: gone.
        ->and($result['sweep'])->toBeNull()
        ->and($result['shimmerFrames'])->toBe(1)
        ->and($result['breathing'])->toBe('none');
});

it('lands the meter straight on the reading when easing is not wanted', function () {
    $this->actingAs($this->user);

    /**
     * Same signal, two preferences. With motion allowed the bar eases towards a
     * sudden drop and lags behind it; with motion reduced it is simply the
     * measurement. Both are the same number of frames of the same audio — only
     * the easing differs.
     */
    $script = withRecorderMotion(<<<'JS'
        recorder.resetMeter();
        recorder.state = 'recording';

        stubAnalyser(96);
        await drawFrames(10);

        stubAnalyser(1);
        recorder.drawWaveform();

        return recorder._smoothedDbfs;
    JS);

    $eased = visit(motionPage())->script($script);
    $instant = visit(motionPage(), ['reducedMotion' => 'reduce'])->script($script);

    // -42 dBFS is what the analyser is actually reporting on that last frame.
    expect($instant)->toBeLessThan(-40)
        ->and($eased)->toBeGreaterThan($instant + 10);
});
