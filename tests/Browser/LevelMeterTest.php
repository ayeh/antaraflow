<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
 * Run a body against the recorder with a fake microphone in place.
 *
 * Headless Chromium has no audio input, so the analyser is stubbed with a
 * square wave whose amplitude the test chooses: the RMS of a square wave at
 * byte amplitude A is exactly A/128, which makes the dBFS the test is asking
 * for arithmetic rather than guesswork. 127 is roughly full scale, 64 is
 * -6 dBFS, 8 is -24 dBFS, 1 is -42 dBFS.
 *
 * requestAnimationFrame is neutered for the duration: the tests drive frames
 * themselves, and a real loop running alongside would paint between assertions.
 */
function withMeter(string $body): string
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

        /**
         * Drive n frames, spaced in real time so attack/release actually advance.
         *
         * Measuring and painting run on separate clocks in production — a timer
         * for the level, requestAnimationFrame for the picture — so a frame here
         * is one turn of each, in that order.
         */
        const drawFrames = async (n) => {
            for (let i = 0; i < n; i++) {
                recorder.sampleTick();
                recorder.drawWaveform();
                await new Promise((resolve) => setTimeout(resolve, 20));
            }
        };

        /** Every distinct rgb triplet on the canvas. */
        const paintedColours = () => {
            const pixels = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;
            const seen = new Set();
            for (let i = 0; i < pixels.length; i += 4) {
                seen.add(pixels[i] + ',' + pixels[i + 1] + ',' + pixels[i + 2]);
            }
            return [...seen];
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

it('renders a flat meter while idle', function () {
    $this->actingAs($this->user);

    visit(route('meetings.show', $this->meeting))
        ->assertScript('Alpine.$data(document.querySelector(\'[x-data^="audioRecorder"]\')).state', 'idle')
        ->assertScript('Alpine.$data(document.querySelector(\'[x-data^="audioRecorder"]\')).audioLevel', 0);
});

it('paints an identical frame in every state that is not recording', function () {
    $this->actingAs($this->user);

    /**
     * This is the regression that matters. The meter used to animate sine waves
     * in ready, countdown and paused, which taught users that a moving waveform
     * says nothing about whether audio is being captured. Reintroduce any motion
     * outside `recording` and the frames stop matching.
     */
    $result = visit(route('meetings.show', $this->meeting))->script(withMeter(<<<'JS'
        stubAnalyser(127);

        const distinctFrames = {};

        for (const state of ['ready', 'countdown', 'paused', 'stopping']) {
            recorder.state = state;

            const frames = [];
            for (let i = 0; i < 6; i++) {
                recorder.drawWaveform();
                frames.push(canvas.toDataURL());
                await new Promise((resolve) => setTimeout(resolve, 25));
            }

            distinctFrames[state] = new Set(frames).size;
        }

        return distinctFrames;
    JS));

    expect($result)->toBe([
        'ready' => 1,
        'countdown' => 1,
        'paused' => 1,
        'stopping' => 1,
    ]);
});

it('adds nothing to the tape unless it is recording', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(withMeter(<<<'JS'
        stubAnalyser(64);
        recorder.resetMeter();

        recorder.state = 'paused';
        await drawFrames(5);
        const whilePaused = recorder._tape.toArray().length;

        recorder.state = 'recording';
        await drawFrames(5);
        const whileRecording = recorder._tape.toArray().length;

        return { whilePaused, whileRecording };
    JS));

    expect($result['whilePaused'])->toBe(0)
        ->and($result['whileRecording'])->toBe(5);
});

it('moves only while audio is actually being captured', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(withMeter(<<<'JS'
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
            recorder.sampleTick();
            recorder.drawWaveform();
            frames.push(canvas.toDataURL());
            await new Promise((resolve) => setTimeout(resolve, 25));
        }

        return { distinct: new Set(frames).size, audioLevel: recorder.audioLevel };
    JS));

    expect($result['distinct'])->toBeGreaterThan(1)
        ->and($result['audioLevel'])->toBeGreaterThan(0);
});

it('scrolls new samples in from the right', function () {
    $this->actingAs($this->user);

    /**
     * A burst of sound after silence has to land at the newest edge — on the
     * canvas, not merely in the array behind it. Draw the same bars right to
     * left and history crawls the wrong way while every data assertion still
     * passes, so this one reads pixels.
     *
     * Thirty frames of a 150-bar meter fill the last fifth of the width; nothing
     * at all should be painted left of that.
     */
    $result = visit(route('meetings.show', $this->meeting))->script(withMeter(<<<'JS'
        recorder.resetMeter();
        recorder.state = 'recording';

        stubAnalyser(96);
        await drawFrames(30);

        const bands = ['13,115,119', '217,119,6', '220,38,38'];
        const pixels = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;
        let leftmost = null;
        let rightmost = null;

        for (let i = 0; i < pixels.length; i += 4) {
            if (!bands.includes(pixels[i] + ',' + pixels[i + 1] + ',' + pixels[i + 2])) {
                continue;
            }

            const x = (i / 4) % canvas.width;
            leftmost = leftmost === null ? x : Math.min(leftmost, x);
            rightmost = rightmost === null ? x : Math.max(rightmost, x);
        }

        const bars = Array.from(recorder._barValues);

        return {
            leftmost,
            rightmost,
            width: canvas.width,
            oldest: bars[0],
            newest: bars[bars.length - 1],
            bars: bars.length,
        };
    JS));

    expect($result['bars'])->toBe(150)
        ->and($result['newest'])->toBeGreaterThan($result['oldest'])
        ->and($result['leftmost'])->toBeGreaterThan(0.75 * $result['width'])
        ->and($result['rightmost'])->toBeGreaterThan(0.98 * $result['width']);
});

it('colours the meter by how loud the signal is', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(withMeter(<<<'JS'
        recorder.state = 'recording';

        const bandFor = async (amplitude) => {
            recorder.resetMeter();
            stubAnalyser(amplitude);
            await drawFrames(25);

            const colours = paintedColours();

            return {
                teal: colours.includes('13,115,119'),
                amber: colours.includes('217,119,6'),
                crimson: colours.includes('220,38,38'),
            };
        };

        return {
            quiet: await bandFor(8),     // -24 dBFS
            hot: await bandFor(64),      //  -6 dBFS
            clipping: await bandFor(127), //  ~0 dBFS
        };
    JS));

    expect($result['quiet'])->toBe(['teal' => true, 'amber' => false, 'crimson' => false])
        ->and($result['hot'])->toBe(['teal' => false, 'amber' => true, 'crimson' => false])
        ->and($result['clipping'])->toBe(['teal' => false, 'amber' => false, 'crimson' => true]);
});

it('holds a peak above the level that produced it', function () {
    $this->actingAs($this->user);

    /**
     * A transient that lasts two frames still has to be readable, so the peak
     * must not follow the release curve straight back down.
     */
    $result = visit(route('meetings.show', $this->meeting))->script(withMeter(<<<'JS'
        recorder.resetMeter();
        recorder.state = 'recording';

        stubAnalyser(127);
        await drawFrames(15);
        const peakAtBurst = recorder._peakDbfs;

        stubAnalyser(1);
        await drawFrames(3);   // ~60ms later, well inside the 800ms hold

        return {
            heldPeak: recorder._peakDbfs,
            peakAtBurst,
            currentLevel: recorder._smoothedDbfs,
        };
    JS));

    expect($result['heldPeak'])->toBe($result['peakAtBurst'])
        ->and($result['heldPeak'])->toBeGreaterThan($result['currentLevel']);
});

it('allocates its buffers once, not once per frame', function () {
    $this->actingAs($this->user);

    /**
     * At sixty frames a second for a two-hour meeting, a buffer allocated in the
     * draw loop is a quarter of a million allocations. Identity is the cheapest
     * way to pin that down.
     */
    $result = visit(route('meetings.show', $this->meeting))->script(withMeter(<<<'JS'
        recorder.resetMeter();
        recorder.state = 'recording';
        stubAnalyser(64);

        await drawFrames(2);
        const first = {
            timeDomain: recorder._timeDomain,
            barValues: recorder._barValues,
        };

        await drawFrames(10);

        return {
            timeDomain: first.timeDomain === recorder._timeDomain,
            barValues: first.barValues === recorder._barValues,
        };
    JS));

    expect($result)->toBe(['timeDomain' => true, 'barValues' => true]);
});

it('keeps the tape at a fixed size however long the meeting runs', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(withMeter(<<<'JS'
        recorder.resetMeter();

        for (let i = 0; i < 12000; i++) {
            recorder._tape.push(-20);
        }

        return recorder._tape.toArray().length;
    JS));

    expect($result)->toBe(3600);
});

it('raises the quiet warning from the recording loop and nowhere else', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(withMeter(<<<'JS'
        let observations = 0;
        recorder._quietWarning = {
            observe: () => { observations++; return true; },
            reset: () => {},
        };
        recorder.showQuietWarning = false;
        stubAnalyser(1);

        recorder.state = 'ready';
        await drawFrames(3);
        const whileReady = { observations, warned: recorder.showQuietWarning };

        recorder.state = 'recording';
        await drawFrames(1);
        const whileRecording = { observations, warned: recorder.showQuietWarning };

        return { whileReady, whileRecording };
    JS));

    expect($result['whileReady'])->toBe(['observations' => 0, 'warned' => false])
        ->and($result['whileRecording'])->toBe(['observations' => 1, 'warned' => true]);
});

it('does not warn about a signal that is perfectly audible', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(withMeter(<<<'JS'
        recorder.showQuietWarning = false;
        recorder.resetMeter();
        recorder.state = 'recording';
        stubAnalyser(64);

        await drawFrames(20);

        return recorder.showQuietWarning;
    JS));

    expect($result)->toBeFalse();
});

it('clears the warning and the tape when the recorder is reset', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(withMeter(<<<'JS'
        recorder.resetMeter();
        recorder.state = 'recording';
        stubAnalyser(64);
        await drawFrames(5);
        recorder.showQuietWarning = true;

        recorder.releaseStream = () => {};
        recorder.resetRecorder();

        return {
            warned: recorder.showQuietWarning,
            tape: recorder._tape.toArray().length,
            audioLevel: recorder.audioLevel,
        };
    JS));

    expect($result)->toBe(['warned' => false, 'tape' => 0, 'audioLevel' => 0]);
});
