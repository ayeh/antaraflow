# Audio Capture Quality Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make distant speakers and quiet voices intelligible in recorded meetings, and make it unmistakable to the user that recording is in progress.

**Architecture:** Three independent phases. Phase 1 fixes the browser capture layer (raw constraints plus our own Web Audio gain chain before Opus encoding). Phase 2 adds a conditional ffmpeg restoration chain inside the existing encode step on the server. Phase 3 turns participant phones into satellite microphones under a new `Domain/MultiMic` bounded context. Phases 1 and 2 ship separately so their effects can be measured independently.

**Tech Stack:** Laravel 12, Alpine 3, Vite 7, Pest 4 (+ browser plugin, added in Task 1), ffmpeg, Web Audio API, MediaRecorder.

**Design document:** `docs/plans/2026-08-02-audio-capture-quality-design.md`

---

## Before you start

Read the design document first. It explains *why* each change is made; this plan only covers *how*. Several decisions look wrong without that context — in particular, disabling `noiseSuppression` and `echoCancellation` is deliberate, not an oversight.

**Conventions in this codebase you must follow:**

- Run `vendor/bin/pint --dirty --format agent` after touching any PHP file
- Run tests with `php artisan test --compact`
- PHP: explicit return types, constructor property promotion, curly braces always, PHPDoc over inline comments
- Never use `env()` outside `config/`
- Prefer editing existing files over creating new ones

**Scope note on Phase 3:** Phases 0-2 below are specified at full task granularity and are ready to execute. Phase 3 is specified at task and interface granularity, but **must be re-planned in detail before execution** — its shape depends on the measurement data that Phases 1 and 2 produce (see Task 22). Do not start Phase 3 until Phase 1 has been in production long enough to gather confidence data.

---

# Phase 0 — Test infrastructure

## Task 1: Install the Pest browser plugin

The repository currently has no JavaScript test capability at all. Phase 1 is almost entirely client-side, so this comes first.

**Files:**
- Modify: `composer.json`
- Modify: `package.json`
- Modify: `.gitignore`

**Step 1: Install the plugin and Playwright**

```bash
composer require pestphp/pest-plugin-browser --dev
npm install playwright@latest
npx playwright install chromium
```

**Step 2: Ignore screenshots**

Add to `.gitignore`:

```
tests/Browser/Screenshots
```

**Step 3: Verify the plugin loads**

Run: `vendor/bin/pest --version`
Expected: version prints with no error about a missing plugin.

**Step 4: Commit**

```bash
git add composer.json composer.lock package.json package-lock.json .gitignore
git commit -m "test: add pest browser plugin for client-side audio tests"
```

---

## Task 2: Add a test harness page for pure audio logic

Browser tests cannot call `getUserMedia` reliably in a headless environment. The solution is to keep all risky logic in pure functions and exercise them through `assertScript()`, with no microphone involved.

**Files:**
- Create: `resources/views/testing/audio-harness.blade.php`
- Modify: `routes/web.php`

**Step 1: Create the harness view**

`resources/views/testing/audio-harness.blade.php`:

```blade
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Audio Harness</title>
    @vite(['resources/js/audio-harness.js'])
</head>
<body>
    <p>audio harness ready</p>
</body>
</html>
```

**Step 2: Create the harness entrypoint**

`resources/js/audio-harness.js`:

```js
/**
 * Test-only entrypoint. Exposes the pure audio helpers on window so browser
 * tests can exercise them with assertScript() without a real microphone.
 */
import * as level from './audio/level.js';
import * as quietWarning from './audio/quiet-warning.js';
import * as tapeBuffer from './audio/tape-buffer.js';

window.audioHarness = { level, quietWarning, tapeBuffer };
```

Add it to `vite.config.js` inputs alongside the existing entries.

**Step 3: Register the route, guarded by environment**

In `routes/web.php`, at the end of the file:

```php
if (app()->environment('local', 'testing')) {
    Route::view('__audio-harness', 'testing.audio-harness')->name('testing.audio-harness');
}
```

**Step 4: Write the smoke test**

Create `tests/Browser/AudioHarnessTest.php`:

```php
<?php

declare(strict_types=1);

it('loads the audio harness', function () {
    visit('/__audio-harness')
        ->assertSee('audio harness ready')
        ->assertNoJavaScriptErrors();
});
```

**Step 5: Run it and watch it fail**

Run: `npm run build && vendor/bin/pest tests/Browser/AudioHarnessTest.php`
Expected: FAIL — the three modules in Step 2 do not exist yet.

**Step 6: Create empty module stubs**

Create `resources/js/audio/level.js`, `resources/js/audio/quiet-warning.js`, `resources/js/audio/tape-buffer.js`, each containing only `export {};` for now.

**Step 7: Run it again**

Run: `npm run build && vendor/bin/pest tests/Browser/AudioHarnessTest.php`
Expected: PASS

**Step 8: Commit**

```bash
git add resources/views/testing resources/js/audio-harness.js resources/js/audio routes/web.php vite.config.js tests/Browser/AudioHarnessTest.php
git commit -m "test: add browser harness for pure audio logic"
```

---

# Phase 1 — Capture layer

## Task 3: Level maths

**Files:**
- Modify: `resources/js/audio/level.js`
- Test: `tests/Browser/AudioLevelTest.php`

**Step 1: Write the failing test**

`tests/Browser/AudioLevelTest.php`:

```php
<?php

declare(strict_types=1);

it('converts linear amplitude to dBFS', function () {
    $page = visit('/__audio-harness');

    $page->assertScript('window.audioHarness.level.toDbfs(1)', 0);
    $page->assertScript('Math.round(window.audioHarness.level.toDbfs(0.5))', -6);
    $page->assertScript('window.audioHarness.level.toDbfs(0)', -100);
});

it('computes rms from a byte time-domain buffer', function () {
    $page = visit('/__audio-harness');

    // A buffer of all 128 is silence: Uint8 time domain is centred on 128.
    $page->assertScript('window.audioHarness.level.rmsFromTimeDomain(new Uint8Array(64).fill(128))', 0);

    // Full-scale square wave alternating between the extremes reads as 1.
    $page->assertScript(
        'Math.round(window.audioHarness.level.rmsFromTimeDomain(new Uint8Array(64).fill(255)))',
        1
    );
});

it('reports clipping only at the very top of the range', function () {
    $page = visit('/__audio-harness');

    $page->assertScript('window.audioHarness.level.isClipping(-0.5)', true);
    $page->assertScript('window.audioHarness.level.isClipping(-6)', false);
});
```

**Step 2: Run to verify it fails**

Run: `npm run build && vendor/bin/pest tests/Browser/AudioLevelTest.php`
Expected: FAIL — `toDbfs is not a function`

**Step 3: Implement**

`resources/js/audio/level.js`:

```js
/** Floor for the dBFS scale; anything quieter is reported as this value. */
export const SILENCE_DBFS = -100;

/** Peaks above this are treated as clipping. */
export const CLIPPING_DBFS = -1;

/**
 * Convert a linear amplitude in the range 0..1 to dBFS.
 */
export function toDbfs(amplitude) {
    if (amplitude <= 0) {
        return SILENCE_DBFS;
    }

    return Math.max(SILENCE_DBFS, 20 * Math.log10(amplitude));
}

/**
 * Root-mean-square of an AnalyserNode byte time-domain buffer, returned as a
 * linear amplitude in the range 0..1. Byte time-domain data is centred on 128.
 */
export function rmsFromTimeDomain(buffer) {
    let sum = 0;

    for (let i = 0; i < buffer.length; i++) {
        const sample = (buffer[i] - 128) / 128;
        sum += sample * sample;
    }

    return Math.sqrt(sum / buffer.length);
}

export function isClipping(peakDbfs) {
    return peakDbfs > CLIPPING_DBFS;
}
```

**Step 4: Run to verify it passes**

Run: `npm run build && vendor/bin/pest tests/Browser/AudioLevelTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add resources/js/audio/level.js tests/Browser/AudioLevelTest.php
git commit -m "feat(audio): add dBFS and RMS level helpers"
```

---

## Task 4: Quiet-voice warning state machine

This is the logic behind "voice too quiet" (design 1d). It must fire once after a sustained quiet period, and never nag.

**Files:**
- Modify: `resources/js/audio/quiet-warning.js`
- Test: `tests/Browser/QuietWarningTest.php`

**Step 1: Write the failing test**

`tests/Browser/QuietWarningTest.php`:

```php
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
```

**Step 2: Run to verify it fails**

Run: `npm run build && vendor/bin/pest tests/Browser/QuietWarningTest.php`
Expected: FAIL — `createQuietWarning is not a function`

**Step 3: Implement**

`resources/js/audio/quiet-warning.js`:

```js
/** Speech below this level will not survive Opus encoding intelligibly. */
export const QUIET_THRESHOLD_DBFS = -40;

/** How long the level must stay low before the user is told. */
export const SUSTAINED_MS = 15_000;

/**
 * Tracks whether the captured level has been too quiet for long enough to be
 * worth interrupting the user. Fires at most once per recording: a warning
 * that repeats gets ignored, and worse, interrupts whoever is running the
 * meeting.
 *
 * @return {{observe: (levelDbfs: number, nowMs: number) => boolean, reset: () => void}}
 */
export function createQuietWarning({
    thresholdDbfs = QUIET_THRESHOLD_DBFS,
    sustainedMs = SUSTAINED_MS,
} = {}) {
    let quietSince = null;
    let alreadyFired = false;

    return {
        observe(levelDbfs, nowMs) {
            if (alreadyFired) {
                return false;
            }

            if (levelDbfs > thresholdDbfs) {
                quietSince = null;

                return false;
            }

            if (quietSince === null) {
                quietSince = nowMs;

                return false;
            }

            if (nowMs - quietSince < sustainedMs) {
                return false;
            }

            alreadyFired = true;

            return true;
        },

        reset() {
            quietSince = null;
            alreadyFired = false;
        },
    };
}
```

**Step 4: Run to verify it passes**

Run: `npm run build && vendor/bin/pest tests/Browser/QuietWarningTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add resources/js/audio/quiet-warning.js tests/Browser/QuietWarningTest.php
git commit -m "feat(audio): add quiet-voice warning state machine"
```

---

## Task 5: Tape ring buffer

Backs the scrolling waveform (design 1f). Must not grow without bound during a two-hour mobile recording.

**Files:**
- Modify: `resources/js/audio/tape-buffer.js`
- Test: `tests/Browser/TapeBufferTest.php`

**Step 1: Write the failing test**

`tests/Browser/TapeBufferTest.php`:

```php
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
    visit('/__audio-harness')->assertScript(<<<'JS'
        (() => {
            const t = window.audioHarness.tapeBuffer.createTape(60);
            for (let i = 0; i < 100000; i++) { t.push(i); }
            return t.toArray().length;
        })()
    JS, 60);
});
```

**Step 2: Run to verify it fails**

Run: `npm run build && vendor/bin/pest tests/Browser/TapeBufferTest.php`
Expected: FAIL — `createTape is not a function`

**Step 3: Implement**

`resources/js/audio/tape-buffer.js`:

```js
/**
 * Fixed-capacity ring buffer holding the visible waveform history. A two-hour
 * recording pushes tens of thousands of samples, so the buffer must never grow
 * with the length of the meeting.
 *
 * @return {{push: (value: number) => void, toArray: () => number[], clear: () => void}}
 */
export function createTape(capacity) {
    const values = new Float32Array(capacity);
    let count = 0;
    let head = 0;

    return {
        push(value) {
            values[head] = value;
            head = (head + 1) % capacity;
            count = Math.min(count + 1, capacity);
        },

        toArray() {
            const out = [];
            const start = count < capacity ? 0 : head;

            for (let i = 0; i < count; i++) {
                out.push(values[(start + i) % capacity]);
            }

            return out;
        },

        clear() {
            count = 0;
            head = 0;
        },
    };
}
```

**Step 4: Run to verify it passes**

Run: `npm run build && vendor/bin/pest tests/Browser/TapeBufferTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add resources/js/audio/tape-buffer.js tests/Browser/TapeBufferTest.php
git commit -m "feat(audio): add fixed-capacity tape ring buffer"
```

---

## Task 6: Raw capture constraints and the gain chain

**Files:**
- Modify: `resources/js/audio-recorder.js:191-212` (`setupStream`)

**Step 1: Replace the constraints and build the chain**

Replace `setupStream()` in `resources/js/audio-recorder.js`:

```js
/**
 * Open the microphone with the browser's call-tuned processing disabled.
 *
 * echoCancellation, noiseSuppression and autoGainControl are designed for one
 * person close to a mic on a video call. In a meeting room they actively
 * destroy the signal we need: noise suppression gates distant speech out as
 * noise, echo cancellation suppresses the room reflections that carry a far
 * speaker's energy, and auto gain pumps between loud and quiet talkers.
 * Disabling echo cancellation also stops Chrome forcing its narrow processing
 * path, so we receive full-band device audio.
 */
async setupStream() {
    this.mediaStream = await navigator.mediaDevices.getUserMedia({
        audio: {
            echoCancellation: false,
            noiseSuppression: false,
            autoGainControl: false,
            voiceIsolation: false,
            channelCount: 1,
            ...(this.selectedDeviceId ? { deviceId: { exact: this.selectedDeviceId } } : {}),
        },
    });

    this.captureStream = this.buildGainChain(this.mediaStream) ?? this.mediaStream;

    this.drawWaveform();
},

/**
 * Raise the signal before it reaches the Opus encoder.
 *
 * MediaRecorder encodes at a low bitrate. A distant speaker sitting at -50
 * dBFS receives almost no bits, and no amount of server-side normalisation
 * recovers that — it only amplifies encoder noise. Gain has to be applied
 * before encoding, which means here.
 *
 * Returns null when Web Audio is unavailable, in which case the caller falls
 * back to the raw stream. A recording must never be silent because of this.
 */
buildGainChain(stream) {
    try {
        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();

        const source = this.audioContext.createMediaStreamSource(stream);

        const highpass = this.audioContext.createBiquadFilter();
        highpass.type = 'highpass';
        highpass.frequency.value = 80;

        const compressor = this.audioContext.createDynamicsCompressor();
        compressor.threshold.value = -35;
        compressor.ratio.value = 4;
        compressor.attack.value = 0.02;
        compressor.release.value = 0.25;

        const gain = this.audioContext.createGain();
        gain.gain.value = 1.5;

        this.analyserNode = this.audioContext.createAnalyser();
        this.analyserNode.fftSize = 2048;

        const destination = this.audioContext.createMediaStreamDestination();

        source.connect(highpass);
        highpass.connect(compressor);
        compressor.connect(gain);
        gain.connect(this.analyserNode);
        this.analyserNode.connect(destination);

        return destination.stream;
    } catch {
        this.audioContext = null;
        this.analyserNode = null;

        return null;
    }
},
```

**Step 2: Point MediaRecorder at the processed stream**

Every `new MediaRecorder(this.mediaStream, ...)` must become `new MediaRecorder(this.captureStream, ...)`. There are three: `beginRecording()` (line ~348), `startChunkCycle()` (line ~432), and the guard in `startNewChunk()` that checks `!this.mediaStream`.

Add `captureStream: null,` and `selectedDeviceId: null,` to the component state block near line 21.

**Step 3: Verify no console errors on the recorder page**

Run: `npm run build && vendor/bin/pest tests/Feature/Domain/Transcription/BrowserRecordingIntegrationTest.php`
Expected: PASS — the existing server-side lifecycle tests must be unaffected.

**Step 4: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add resources/js/audio-recorder.js
git commit -m "fix(audio): capture raw mic and apply our own gain chain

Browser AEC/NS/AGC are tuned for a single near-field talker on a call and
gate out distant, quiet speech in a meeting room. Replace them with a
highpass/compressor/gain chain applied before Opus encoding, where the
signal can still be recovered."
```

---

## Task 7: Open the microphone lazily so the browser indicator means something

**Files:**
- Modify: `resources/js/audio-recorder.js:86` (`init`), `:150-162` (`checkExistingPermission`)
- Test: `tests/Browser/RecordingStateTest.php`

**Step 1: Write the failing test**

`tests/Browser/RecordingStateTest.php` — assert the mic is not opened on page load. The component exposes `micOpen` for this.

```php
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
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
});

it('does not open the microphone on page load', function () {
    $this->actingAs($this->user);

    visit(route('meetings.show', $this->meeting))
        ->assertScript('window.__recorder().micOpen', false)
        ->assertNoJavaScriptErrors();
});
```

Expose the helper in `init()`: `window.__recorder = () => this;` guarded by `import.meta.env.DEV`.

**Step 2: Run to verify it fails**

Run: `npm run build && vendor/bin/pest tests/Browser/RecordingStateTest.php`
Expected: FAIL — `micOpen` is undefined, or true.

**Step 3: Implement**

Remove the `setupStream()` call from `checkExistingPermission()`. It should only record whether permission exists, not act on it:

```js
/**
 * Note whether permission was already granted, but do not open the microphone.
 *
 * Opening it here lights the browser's own recording indicator the moment the
 * page loads — long before the user presses record — which strips that
 * indicator of any meaning. The stream is opened at mic-check time instead,
 * and the existing countdown hides the ~200-500ms it costs to reopen.
 */
async checkExistingPermission() {
    try {
        if (navigator.permissions?.query) {
            const result = await navigator.permissions.query({ name: 'microphone' });
            this.permissionGranted = result.state === 'granted';
        }
    } catch {
        // Firefox does not support querying microphone permission.
    }
},
```

Add `micOpen: false,` and `permissionGranted: false,` to component state. Set `this.micOpen = true` at the end of `setupStream()`, and add a `releaseStream()` that stops all tracks, closes the `AudioContext`, and sets `micOpen = false`. Call `releaseStream()` from `resetRecorder()` and after a completed upload.

**Step 4: Run to verify it passes**

Run: `npm run build && vendor/bin/pest tests/Browser/RecordingStateTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add resources/js/audio-recorder.js tests/Browser/RecordingStateTest.php
git commit -m "fix(audio): open mic on demand so the browser indicator means recording"
```

---

## Task 8: Microphone picker

**Files:**
- Modify: `resources/js/audio-recorder.js`
- Modify: the recorder Blade partial that renders the controls

**Step 1: Add enumeration**

```js
/**
 * List available inputs. Device labels are only populated after permission has
 * been granted, so this runs after setupStream(), never before.
 */
async loadInputDevices() {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();
        this.inputDevices = devices.filter((d) => d.kind === 'audioinput');
    } catch {
        this.inputDevices = [];
    }
},

async selectInputDevice(deviceId) {
    this.selectedDeviceId = deviceId;
    localStorage.setItem('antaranote-mic-device', deviceId);

    this.releaseStream();
    await this.setupStream();
},
```

Add `inputDevices: [],` to state, and read the saved id in `init()`:
`this.selectedDeviceId = localStorage.getItem('antaranote-mic-device');`

Call `this.loadInputDevices()` at the end of `setupStream()`.

**Step 2: Render the picker**

Add a `<select>` bound to `selectedDeviceId` with `@change="selectInputDevice($event.target.value)"`, shown only when `inputDevices.length > 1`.

**Step 3: Verify manually and with a smoke test**

Run: `npm run build && vendor/bin/pest tests/Browser/RecordingStateTest.php`
Expected: PASS with no JavaScript errors.

**Step 4: Commit**

```bash
git commit -am "feat(audio): let the user choose which microphone to record from"
```

---

## Task 9: Real level meter and scrolling tape

**Files:**
- Modify: `resources/js/audio-recorder.js:215-310` (`drawWaveform`)

**Step 1: Replace the synthetic waveform**

Delete the layered sine-wave block at lines ~290-297 entirely. The new `drawWaveform()`:

- reads `getByteTimeDomainData` into a reused buffer
- converts to dBFS via `level.rmsFromTimeDomain` and `level.toDbfs`
- pushes one sample per frame into a `createTape(...)` sized for 60 seconds of history
- renders the tape scrolling right-to-left
- applies attack ~30ms and release ~200ms smoothing to the displayed value
- holds a peak line for 800ms before letting it fall
- colours by level: teal below -12 dBFS, amber to -1, crimson above
- **renders nothing but a flat line when `state` is not `recording`**

Feed the quiet warning each frame:

```js
if (this.state === 'recording' && this._quietWarning.observe(levelDbfs, performance.now())) {
    this.showQuietWarning = true;
}
```

Initialise `this._quietWarning = createQuietWarning()` in `beginRecording()` and call `.reset()` in `resetRecorder()`.

**Step 2: Test that the meter is dead while idle**

Add to `tests/Browser/RecordingStateTest.php`:

```php
it('renders a flat meter while idle', function () {
    $this->actingAs($this->user);

    visit(route('meetings.show', $this->meeting))
        ->assertScript('window.__recorder().state', 'idle')
        ->assertScript('window.__recorder().audioLevel', 0);
});
```

**Step 3: Run**

Run: `npm run build && vendor/bin/pest tests/Browser/RecordingStateTest.php`
Expected: PASS

**Step 4: Commit**

```bash
git commit -am "feat(audio): scrolling tape waveform driven by measured level

Replaces the synthetic sine waves, which animated in every state and so
taught users that motion carries no meaning."
```

---

## Task 10: Status pill

**Files:**
- Modify: the recorder Blade partial
- Modify: `resources/css/app.css` (or the recorder partial's `<style>` block)

**Step 1: Add the pill**

Worded, not colour-only: `● MERAKAM 12:34`. Sticky when the recorder scrolls out of view. Sits above the mobile bottom nav — check the z-index and offset introduced by commit `69da68d`.

The `●` pulses at 1 Hz **driven by the timer tick**, not an independent CSS animation. In the existing `timerInterval` callback, toggle `this.pulseOn = !this.pulseOn` so the beat and the digit change land together.

**Step 2: Test**

```php
it('shows a worded recording pill only while recording', function () {
    $this->actingAs($this->user);

    $page = visit(route('meetings.show', $this->meeting));

    $page->assertDontSee('MERAKAM');
    $page->script("window.__recorder().state = 'recording'");
    $page->assertSee('MERAKAM');
});
```

**Step 3: Run**

Run: `npm run build && vendor/bin/pest tests/Browser/RecordingStateTest.php`
Expected: PASS

**Step 4: Commit**

```bash
git commit -am "feat(audio): worded recording pill with timer-synced pulse"
```

---

## Task 11: Tab title and favicon

**Files:**
- Modify: `resources/js/audio-recorder.js`

**Step 1: Implement**

```js
/**
 * Mirror recording state into the tab title and favicon, so the signal
 * survives the user switching to another tab entirely.
 */
syncTabIndicator() {
    if (this.state === 'recording') {
        document.title = `● ${this.formattedTimer} — ${this.config.i18n?.recording || 'Recording'}`;
        this.setFavicon(true);

        return;
    }

    document.title = this._originalTitle;
    this.setFavicon(false);
},
```

Capture `this._originalTitle = document.title` in `init()`. Call `syncTabIndicator()` from the timer tick and from every state transition. `setFavicon()` swaps the `href` on `link[rel="icon"]` between the normal icon and a red-dotted variant, restoring the original on cleanup.

**Step 2: Test**

```php
it('marks the tab title while recording', function () {
    $this->actingAs($this->user);

    $page = visit(route('meetings.show', $this->meeting));
    $page->script("window.__recorder().state = 'recording'; window.__recorder().syncTabIndicator()");
    $page->assertTitleContains('●');
});
```

**Step 3: Run**

Run: `npm run build && vendor/bin/pest tests/Browser/RecordingStateTest.php`
Expected: PASS

**Step 4: Commit**

```bash
git commit -am "feat(audio): mark tab title and favicon while recording"
```

---

## Task 12: Wake lock

**Files:**
- Modify: `resources/js/audio-recorder.js`

**Step 1: Implement**

```js
/**
 * Keep the screen on while recording. Besides keeping the indicator visible,
 * this materially reduces the chance of a mobile browser suspending the
 * recorder when the screen locks.
 */
async acquireWakeLock() {
    try {
        this._wakeLock = await navigator.wakeLock?.request('screen');
    } catch {
        // Wake lock is best-effort; recording continues without it.
    }
},

releaseWakeLock() {
    this._wakeLock?.release().catch(() => {});
    this._wakeLock = null;
},
```

Call `acquireWakeLock()` in `beginRecording()` and `releaseWakeLock()` in `cleanup()` and on stop. Re-acquire in the existing `visibilitychange` handler, since wake locks are dropped when a tab is hidden.

**Step 2: Run the full suite**

Run: `npm run build && vendor/bin/pest tests/Browser`
Expected: PASS

**Step 3: Commit**

```bash
git commit -am "feat(audio): hold a screen wake lock while recording"
```

---

## Task 13: Pre-flight microphone check

**Files:**
- Modify: `resources/js/audio-recorder.js`
- Modify: the recorder Blade partial

**Step 1: Implement**

A five-second check that opens the stream, samples the level, and returns a verdict from the same thresholds as the live warning. This is also the moment the microphone is first opened (Task 7) and the device list is populated (Task 8).

```js
async runMicCheck() {
    this.state = 'checking';
    await this.setupStream();

    const samples = [];
    const started = performance.now();

    while (performance.now() - started < 5000) {
        samples.push(this.currentLevelDbfs());
        await new Promise((r) => setTimeout(r, 100));
    }

    const speech = samples.filter((d) => d > -60);
    const median = speech.length
        ? speech.sort((a, b) => a - b)[Math.floor(speech.length / 2)]
        : -100;

    this.micCheckResult = median > -40 ? 'good' : 'quiet';
    this.state = 'ready';
},
```

**Step 2: Wire it into the UI**

"Test mic" button before recording; the verdict renders as a green or amber message with actionable wording for the quiet case.

**Step 3: Run**

Run: `npm run build && vendor/bin/pest tests/Browser`
Expected: PASS

**Step 4: Commit**

```bash
git commit -am "feat(audio): pre-flight mic check with an actionable verdict"
```

---

## Task 14: Micro-interactions and reduced motion

**Files:**
- Modify: the recorder Blade partial (`<style>` block)
- Modify: `resources/js/audio-recorder.js`

**Step 1: Add the keyframes**

Match the existing convention from `resources/views/components/confirm-modal.blade.php`: spring easing `cubic-bezier(.34,1.56,.64,1)` at ~.35s. Reuse `_cm-icon` and `_cm-strip` for the completion state and `_cm-shake` for the quiet warning.

New animations:
- countdown digit pop, colour walking teal `#0D7377` → amber `#D97706` → crimson `#DC2626`
- record button circle → rounded square morph on start
- tape wake sweep (left-to-right, ~400ms) on start; collapse sweep (right-to-left) on stop
- processing shimmer travelling left-to-right
- idle button breathing: 2.5s cycle, 3% scale, **button only** — the tape stays flat while idle

**Step 2: Honour reduced motion**

```css
@media (prefers-reduced-motion: reduce) {
    ._rec-spring,
    ._rec-sweep,
    ._rec-breathe,
    ._rec-shimmer {
        animation: none !important;
        transition: none !important;
    }
}
```

The level meter keeps updating under reduced motion — it is data, not decoration. Only its spring and decay easing is dropped.

**Step 3: Test**

```php
it('has no accessibility issues on the recorder', function () {
    $this->actingAs($this->user);

    visit(route('meetings.show', $this->meeting))
        ->assertNoAccessibilityIssues()
        ->assertNoJavaScriptErrors();
});
```

**Step 4: Run**

Run: `npm run build && vendor/bin/pest tests/Browser`
Expected: PASS

**Step 5: Commit**

```bash
git commit -am "feat(audio): information-bearing micro-interactions for the recorder"
```

---

## Task 15: Ship Phase 1 and start gathering data

Deploy Phase 1 alone. Do not start Phase 2 until there is enough `confidence_score` data to compare against — shipping both together means never learning which one mattered (design, "Shipping order").

---

# Phase 2 — Server-side ffmpeg chain

## Task 16: Loudness measurement value object

**Files:**
- Create: `app/Domain/Transcription/DTOs/LoudnessMeasurement.php`
- Test: `tests/Unit/Domain/Transcription/LoudnessMeasurementTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Transcription\DTOs\LoudnessMeasurement;

it('parses the loudnorm json ffmpeg prints to stderr', function () {
    $stderr = <<<'TXT'
    [Parsed_loudnorm_0 @ 0x7f] 
    {
        "input_i" : "-34.21",
        "input_tp" : "-9.80",
        "input_lra" : "18.40",
        "input_thresh" : "-44.55",
        "target_offset" : "0.30"
    }
    TXT;

    $measurement = LoudnessMeasurement::fromFfmpegOutput($stderr);

    expect($measurement->integrated)->toBe(-34.21)
        ->and($measurement->truePeak)->toBe(-9.80)
        ->and($measurement->range)->toBe(18.40)
        ->and($measurement->threshold)->toBe(-44.55)
        ->and($measurement->offset)->toBe(0.30);
});

it('returns null when ffmpeg printed no json', function () {
    expect(LoudnessMeasurement::fromFfmpegOutput('ffmpeg: command not found'))->toBeNull();
});
```

**Step 2: Run to verify it fails**

Run: `php artisan test --compact --filter=LoudnessMeasurement`
Expected: FAIL — class not found

**Step 3: Implement**

Readonly class with promoted properties and a static `fromFfmpegOutput(string $stderr): ?self` that finds the last `{...}` block and decodes it, returning `null` on any failure.

**Step 4: Run to verify it passes**

Run: `php artisan test --compact --filter=LoudnessMeasurement`
Expected: PASS

**Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Transcription/DTOs/LoudnessMeasurement.php tests/Unit/Domain/Transcription/LoudnessMeasurementTest.php
git commit -m "feat(transcription): parse loudnorm measurement output"
```

---

## Task 17: Conditional filter chain selection

This is the highest-value test in Phase 2. Over-processing clean audio makes it worse, so the chain must follow the measurement.

**Files:**
- Create: `app/Domain/Transcription/Services/AudioEnhancer.php`
- Test: `tests/Unit/Domain/Transcription/AudioEnhancerChainTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Transcription\DTOs\LoudnessMeasurement;
use App\Domain\Transcription\Services\AudioEnhancer;

function measurement(float $integrated, float $range): LoudnessMeasurement
{
    return new LoudnessMeasurement($integrated, -9.0, $range, -44.0, 0.0);
}

it('always applies a highpass', function () {
    $chain = app(AudioEnhancer::class)->chainFor(measurement(-18.0, 6.0));

    expect($chain)->toContain('highpass=f=80');
});

it('compresses a recording that is far too quiet', function () {
    $chain = app(AudioEnhancer::class)->chainFor(measurement(-34.0, 6.0));

    expect($chain)->toContain('acompressor');
});

it('compresses when loud and quiet speakers are far apart', function () {
    $chain = app(AudioEnhancer::class)->chainFor(measurement(-18.0, 18.0));

    expect($chain)->toContain('acompressor');
});

it('leaves healthy audio alone apart from normalisation', function () {
    $chain = app(AudioEnhancer::class)->chainFor(measurement(-18.0, 6.0));

    expect($chain)->not->toContain('acompressor');
});

it('always normalises last', function () {
    $chain = app(AudioEnhancer::class)->chainFor(measurement(-34.0, 18.0));

    expect(array_key_last(explode(',', $chain)))->not->toBeNull()
        ->and(str_ends_with($chain, 'print_format=summary'))->toBeTrue();
});

it('never trims silence, which would desynchronise segment timestamps', function () {
    $chain = app(AudioEnhancer::class)->chainFor(measurement(-40.0, 20.0));

    expect($chain)->not->toContain('silenceremove');
});
```

**Step 2: Run to verify it fails**

Run: `php artisan test --compact --filter=AudioEnhancerChain`
Expected: FAIL — class not found

**Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Services;

use App\Domain\Transcription\DTOs\LoudnessMeasurement;

/**
 * Builds the ffmpeg filter chain that restores quiet and distant speech before
 * transcription.
 *
 * The chain is chosen from measurement rather than applied uniformly, because
 * over-processing audio that is already clean makes transcription worse. The
 * measurement is free: loudnorm's first pass has to run anyway.
 */
class AudioEnhancer
{
    /** Below this integrated loudness a recording is genuinely too quiet. */
    private const QUIET_LUFS = -30.0;

    /** Above this loudness range, loud and quiet speakers are far apart. */
    private const WIDE_RANGE_LU = 15.0;

    /** Above this loudness no restorative compression is warranted. */
    private const HEALTHY_LUFS = -23.0;

    private const TARGET = 'I=-16:TP=-1.5:LRA=11';

    public function chainFor(LoudnessMeasurement $measurement): string
    {
        $filters = ['highpass=f=80'];

        if ($this->needsCompression($measurement)) {
            $filters[] = 'acompressor=threshold=-35dB:ratio=4:attack=20:release=250:makeup=2';
        }

        $filters[] = sprintf(
            'loudnorm=%s:measured_I=%s:measured_TP=%s:measured_LRA=%s:measured_thresh=%s:offset=%s:linear=true:print_format=summary',
            self::TARGET,
            $measurement->integrated,
            $measurement->truePeak,
            $measurement->range,
            $measurement->threshold,
            $measurement->offset,
        );

        return implode(',', $filters);
    }

    private function needsCompression(LoudnessMeasurement $measurement): bool
    {
        if ($measurement->integrated < self::QUIET_LUFS) {
            return true;
        }

        if ($measurement->range > self::WIDE_RANGE_LU) {
            return true;
        }

        return $measurement->integrated < self::HEALTHY_LUFS;
    }

    /** Filter string for the measurement pass, which writes JSON to stderr. */
    public function measurementChain(): string
    {
        return 'highpass=f=80,loudnorm='.self::TARGET.':print_format=json';
    }
}
```

**Step 4: Run to verify it passes**

Run: `php artisan test --compact --filter=AudioEnhancerChain`
Expected: PASS

**Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Domain/Transcription/Services/AudioEnhancer.php tests/Unit/Domain/Transcription/AudioEnhancerChainTest.php
git commit -m "feat(transcription): choose the ffmpeg chain from loudness measurement"
```

---

## Task 18: Run the measurement pass

**Files:**
- Modify: `app/Domain/Transcription/Services/AudioEnhancer.php`
- Test: `tests/Feature/Domain/Transcription/Services/AudioEnhancerMeasureTest.php`

**Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Domain\Transcription\Services\AudioEnhancer;
use Illuminate\Support\Facades\Process;

it('returns null when ffmpeg is unavailable', function () {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'not found', exitCode: 127)]);

    expect(app(AudioEnhancer::class)->measure('/tmp/whatever.webm'))->toBeNull();
});

it('returns null when ffmpeg times out rather than throwing', function () {
    Process::fake(['*' => fn () => throw new \Symfony\Component\Process\Exception\ProcessTimedOutException(
        new \Symfony\Component\Process\Process([]), 1
    )]);

    expect(app(AudioEnhancer::class)->measure('/tmp/whatever.webm'))->toBeNull();
});
```

**Step 2: Run to verify it fails**

Run: `php artisan test --compact --filter=AudioEnhancerMeasure`
Expected: FAIL — `measure()` not defined

**Step 3: Implement**

`measure(string $filePath): ?LoudnessMeasurement` runs:

```
ffmpeg -hide_banner -nostats -i {file} -af {measurementChain()} -f null -
```

with a 300-second timeout, wrapped in try/catch. It returns `LoudnessMeasurement::fromFfmpegOutput($result->errorOutput())`, or `null` on any failure — logging a warning, following the pattern already in `AudioStorageService.php:115`.

**Step 4: Run to verify it passes**

Run: `php artisan test --compact --filter=AudioEnhancerMeasure`
Expected: PASS

**Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git commit -am "feat(transcription): measure loudness before choosing a chain"
```

---

## Task 19: Wire the chain into the encode step

**Files:**
- Modify: `app/Domain/Transcription/Jobs/ProcessTranscriptionJob.php:52-128` (`handle`), `:222-241` (`encodeToOpus`)
- Test: `tests/Feature/Domain/Transcription/Jobs/` (extend the existing job test)

**Step 1: Write the failing test**

Assert that (a) a small file now goes through ffmpeg where it previously did not, and (b) transcription still succeeds when ffmpeg fails entirely.

```php
it('enhances even small recordings', function () {
    Process::fake();
    // ... arrange a small AudioTranscription ...

    (new ProcessTranscriptionJob($transcription))->handle(app(TranscriberFactory::class));

    Process::assertRan(fn ($process) => str_contains($process->command, 'loudnorm'));
});

it('still transcribes when ffmpeg is missing', function () {
    Process::fake(['*' => Process::result(exitCode: 127, errorOutput: 'not found')]);
    // ... arrange ...

    (new ProcessTranscriptionJob($transcription))->handle(app(TranscriberFactory::class));

    expect($transcription->fresh()->status)->toBe(TranscriptionStatus::Completed);
});
```

**Step 2: Run to verify it fails**

Run: `php artisan test --compact --filter=ProcessTranscription`
Expected: FAIL on the first test — small files currently bypass ffmpeg.

**Step 3: Implement**

In `handle()`, replace the size-gated compression block (line 75) with an always-on enhance-and-encode call. `encodeToOpus()` gains a `?string $filterChain` parameter, inserted as `-af {chain}` before the codec flags. When `measure()` returns `null`, the filter chain is skipped and the original file is used unchanged.

Small files use `MAX_BITRATE`; the existing retry loop still lowers the bitrate for anything over 25 MB, and because each retry re-encodes from the original source there is no generational loss.

**Step 4: Run to verify it passes**

Run: `php artisan test --compact --filter=ProcessTranscription`
Expected: PASS

**Step 5: Run the whole suite**

Run: `php artisan test --compact`
Expected: PASS, except the pre-existing `SubscriptionServiceTest` failure noted in project memory.

**Step 6: Commit**

```bash
vendor/bin/pint --dirty --format agent
git commit -am "feat(transcription): restore quiet speech before sending to whisper"
```

---

## Task 20: Persist the measurement for verification

**Files:**
- Create: migration `add_loudness_measurement_to_audio_transcriptions_table`
- Modify: `app/Domain/Transcription/Models/AudioTranscription.php`
- Modify: `app/Domain/Transcription/Jobs/ProcessTranscriptionJob.php`

**Step 1: Create the migration**

```bash
php artisan make:migration add_loudness_measurement_to_audio_transcriptions_table --no-interaction
```

Add two nullable doubles, `measured_loudness` and `measured_loudness_range`. No timestamp columns here — but see Task 26 for the trap that applies when they are added.

**Step 2: Store them**

Write both from the measurement in `handle()`, before transcription runs.

**Step 3: Test**

```php
it('records the loudness measurement on the transcription', function () {
    // ... arrange with a faked ffmpeg measurement output ...

    expect($transcription->fresh()->measured_loudness)->toBe(-34.21);
});
```

**Step 4: Run**

Run: `php artisan test --compact --filter=ProcessTranscription`
Expected: PASS

**Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git commit -am "feat(transcription): persist loudness measurement for before/after comparison"
```

---

## Task 21: Ship Phase 2

Deploy. Note the new cost: every recording now runs a measurement pass plus an encode pass, roughly 30-60 seconds of CPU for an hour-long meeting, where files under 25 MB previously bypassed ffmpeg entirely. Watch queue depth after release.

---

## Task 22: Read the data before planning Phase 3

Compare mean `confidence_score` before and after Phases 1 and 2, and cross-reference `measured_loudness`:

- Complaints persist **and** `measured_loudness` is still low → the microphone is not capturing. Proceed to Phase 3.
- `measured_loudness` is healthy **but** confidence is still low → level is not the problem; a paid enhancement model becomes worth evaluating instead.

Write the answer into the design document before starting Phase 3.

---

# Phase 3 — Participant phones as satellite mics

> **Re-plan before executing.** The tasks below fix the interfaces and the test strategy, but the detailed steps should be written once Task 22 has confirmed Phase 3 is the right investment.

**Bounded context:** `app/Domain/MultiMic/`

| Task | Summary | Test strategy |
|---|---|---|
| 23 | `meeting_mic_devices` and `mic_device_chunks` migrations, models, factories | Factory + relationship tests |
| 24 | Join flow: "make this phone a mic" screen after QR registration, reusing the public token route pattern at `routes/web.php:79` | Feature test on the token route, including the consent gate |
| 25 | Satellite chunk upload endpoint, mirroring `AudioChunkController` | Feature test with `UploadedFile::fake()` |
| 26 | **Migration audit** — every time column must declare its default explicitly | See below |
| 27 | Cross-correlation alignment, per chunk | Pure unit test: synthetic signal shifted by a known offset, assert recovery |
| 28 | Loudest-stream selection with 3 dB hysteresis and 1 s dwell | Pure unit test on synthetic RMS arrays |
| 29 | `StitchMicChunksJob` producing the master track plus ownership map | Feature test with faked `Process` |
| 30 | Ownership map replaces `assignSpeakers()` when satellites are present | Assert real names appear on segments |
| 31 | Privacy controls: always-visible indicator, stop button, auto-stop on session end, delete satellites after successful stitch | Feature tests per control; auto-stop is the critical one |

### Task 26 in detail — the trap that tests cannot catch

A bare `$table->timestamp('joined_at')` silently acquires `ON UPDATE CURRENT_TIMESTAMP` on production MySQL. SQLite, used in tests, does not reproduce this.

For ordinary tables that corrupts data. For **these** tables it is fatal: a timestamp shifted by 8 hours destroys chunk alignment outright, producing a stitched track that is subtly wrong in a way no test would flag. Every time column in `meeting_mic_devices` and `mic_device_chunks` must declare its default explicitly:

```php
$table->timestamp('joined_at')->nullable()->default(null);
$table->timestamp('last_seen_at')->nullable()->default(null);
```

Verify against a real MySQL connection, not SQLite, before deploying.

### Non-negotiable across all Phase 3 tasks

**Satellites are additive, never required.** The laptop recording is the floor. iOS Safari suspends `MediaRecorder` when the screen locks or the tab backgrounds, so satellite failure is expected to be common, not rare. Every stitching path must produce a valid result when zero satellites contributed — identical to today's behaviour.

---

## Summary of files

**Created:**
- `resources/js/audio/level.js`, `quiet-warning.js`, `tape-buffer.js`
- `resources/js/audio-harness.js`, `resources/views/testing/audio-harness.blade.php`
- `app/Domain/Transcription/DTOs/LoudnessMeasurement.php`
- `app/Domain/Transcription/Services/AudioEnhancer.php`
- `app/Domain/MultiMic/**` (Phase 3)
- `tests/Browser/*`, `tests/Unit/Domain/Transcription/*`

**Modified:**
- `resources/js/audio-recorder.js` — the bulk of Phase 1
- `app/Domain/Transcription/Jobs/ProcessTranscriptionJob.php` — Phase 2
- `routes/web.php`, `vite.config.js`, `composer.json`, `package.json`
- The recorder Blade partial — pill, picker, micro-interactions
