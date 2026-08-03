<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The wizard opens on step 1, which leaves the whole recorder behind a
 * `display: none` ancestor and makes every visibility assertion pass for the
 * wrong reason. Open step 3, where the recorder lives.
 */
function recorderStep(): string
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
 * Run a body against the recorder with a microphone of a chosen loudness.
 *
 * Headless Chromium has no audio input, so setupStream() is replaced with one
 * that installs a square-wave analyser and starts the real animation loop. The
 * check then measures whatever that analyser reports, through the component's
 * own level pipeline — the same path a real microphone takes.
 *
 * The RMS of a square wave at byte amplitude A is exactly A/128, so the dBFS
 * the test asks for is arithmetic rather than guesswork: 127 ≈ 0 dBFS, 64 is
 * -6, 8 is -24, 2 is -36, 1 is -42.
 */
function withMicCheck(string $body): string
{
    return <<<JS
    async () => {
        const recorder = window.Alpine.\$data(document.querySelector('[x-data^="audioRecorder"]'));

        const fakeMicrophone = (amplitude) => {
            recorder.setupStream = async () => {
                recorder.micOpen = true;
                recorder.captureStream = { getTracks: () => [] };
                recorder.analyserNode = {
                    fftSize: 2048,
                    getByteTimeDomainData: (buffer) => {
                        for (let i = 0; i < buffer.length; i++) {
                            buffer[i] = 128 + (i % 2 === 0 ? amplitude : -amplitude);
                        }
                    },
                };
                recorder.startWaveform();
            };
        };

        /** A microphone that refuses to open, the way a denied permission does. */
        const brokenMicrophone = (name) => {
            recorder.setupStream = async () => {
                const error = new Error(name);
                error.name = name;
                throw error;
            };
        };

        recorder.releaseStream = () => { recorder.micOpen = false; };

        try {
            {$body}
        } finally {
            recorder.cancelMicCheck();
        }
    }
    JS;
}

it('tells the user before the meeting that the microphone sounds good', function () {
    $this->actingAs($this->user);

    $page = visit(recorderStep());

    $state = $page->script(withMicCheck(<<<'JS'
        fakeMicrophone(64);   // -6 dBFS, comfortably audible

        await recorder.runMicCheck(400);

        return { state: recorder.state, verdict: recorder.micCheckResult };
    JS));

    expect($state['state'])->toBe('ready')
        ->and($state['verdict'])->toBe('good');

    // The verdict has to reach the screen, not merely the component.
    $page->assertVisible('#recorder-mic-verdict')
        ->assertSee('Microphone sounds good')
        ->assertNoJavaScriptErrors();
});

it('warns about a quiet room while there is still time to move the laptop', function () {
    $this->actingAs($this->user);

    $page = visit(recorderStep());

    $state = $page->script(withMicCheck(<<<'JS'
        fakeMicrophone(1);   // -42 dBFS: audible, far too quiet to transcribe

        await recorder.runMicCheck(400);

        return { state: recorder.state, verdict: recorder.micCheckResult };
    JS));

    expect($state['state'])->toBe('ready')
        ->and($state['verdict'])->toBe('quiet');

    /**
     * "Too quiet" on its own leaves the user exactly where they were. What has
     * to be on screen is something they can do about it, and a way back to the
     * check once they have done it.
     */
    $page->assertVisible('#recorder-mic-verdict')
        ->assertSee('Too quiet to transcribe reliably.')
        ->assertSee('Move the laptop closer to whoever will be speaking.')
        ->assertSee('Test again')
        ->assertNoJavaScriptErrors();
});

it('treats a room with nothing but silence in it as too quiet', function () {
    $this->actingAs($this->user);

    $result = visit(recorderStep())->script(withMicCheck(<<<'JS'
        fakeMicrophone(0);   // silence: a muted or dead microphone

        await recorder.runMicCheck(400);

        return recorder.micCheckResult;
    JS));

    expect($result)->toBe('quiet');
});

it('shows the reason instead of hanging when the microphone will not open', function () {
    $this->actingAs($this->user);

    $page = visit(recorderStep());

    /**
     * The proposal left `state` on 'checking' for ever when setupStream()
     * rejected: a spinner with no end and no explanation, while the actual
     * cause — denied, unplugged, in use by another app — never reached the
     * person who could fix it.
     */
    $state = $page->script(withMicCheck(<<<'JS'
        brokenMicrophone('NotReadableError');

        await recorder.runMicCheck(400);

        return {
            state: recorder.state,
            verdict: recorder.micCheckResult,
            errorMessage: recorder.errorMessage,
        };
    JS));

    expect($state['state'])->toBe('error')
        ->and($state['verdict'])->toBeNull()
        ->and($state['errorMessage'])->toContain('in use by another application');

    $page->assertSee('Microphone is in use by another application.')
        ->assertMissing('#recorder-mic-verdict')
        ->assertNoJavaScriptErrors();
});

it('counts the wait down and lets the user walk away from it', function () {
    $this->actingAs($this->user);

    $page = visit(recorderStep());

    /**
     * Five seconds of staring at nothing is the difference between a check
     * people run and a check they abandon. The countdown proves the wait is
     * finite; Cancel proves they are not trapped in it.
     */
    $result = $page->script(withMicCheck(<<<'JS'
        fakeMicrophone(64);

        const check = recorder.runMicCheck(5000);
        await new Promise((resolve) => setTimeout(resolve, 300));

        const whileChecking = {
            state: recorder.state,
            remaining: recorder.micCheckRemaining,
            visible: !!document.querySelector('#recorder-mic-check'),
            cancelVisible: [...document.querySelectorAll('button')]
                .some((b) => b.offsetParent !== null && b.textContent.trim() === 'Cancel'),
        };

        recorder.cancelMicCheck();
        await check;

        return {
            whileChecking,
            afterCancel: { state: recorder.state, verdict: recorder.micCheckResult },
        };
    JS));

    expect($result['whileChecking']['state'])->toBe('checking')
        ->and($result['whileChecking']['remaining'])->toBeGreaterThan(0)
        ->and($result['whileChecking']['remaining'])->toBeLessThanOrEqual(5)
        // The start/test buttons are replaced by the in-flight block, so the
        // check cannot be started twice over.
        ->and($result['whileChecking']['visible'])->toBeFalse()
        ->and($result['whileChecking']['cancelVisible'])->toBeTrue()
        ->and($result['afterCancel']['state'])->toBe('ready')
        ->and($result['afterCancel']['verdict'])->toBeNull();

    $page->assertNoJavaScriptErrors();
});

it('keeps the meter alive during the check so the wait shows something true', function () {
    $this->actingAs($this->user);

    /**
     * The meter is the only evidence the user has that the check is listening
     * at all. It reads the same measurement the verdict is computed from, so a
     * dead meter here would mean a verdict about nothing.
     */
    $result = visit(recorderStep())->script(withMicCheck(<<<'JS'
        fakeMicrophone(64);

        const canvas = document.querySelector('[x-data^="audioRecorder"] canvas');
        const check = recorder.runMicCheck(600);

        await new Promise((resolve) => setTimeout(resolve, 200));
        const early = canvas.toDataURL();
        const level = recorder.audioLevel;

        await new Promise((resolve) => setTimeout(resolve, 250));
        const later = canvas.toDataURL();

        await check;

        return { moved: early !== later, level, samples: recorder._tape.toArray().length };
    JS));

    expect($result['moved'])->toBeTrue()
        ->and($result['level'])->toBeGreaterThan(0)
        ->and($result['samples'])->toBeGreaterThan(0);
});

it('never lets a mic check pass for a recording', function () {
    $this->actingAs($this->user);

    $page = visit(recorderStep());

    /**
     * 'checking' is a new state, and every list that switches on state had to
     * be audited for it. The ones that matter: it is not a recording, so no
     * pill, no timer, no tab marker and no processing spinner; and the mic
     * picker stays live, because swapping microphone is the most likely thing
     * a bad verdict should make somebody do.
     */
    $result = $page->script(withMicCheck(<<<'JS'
        fakeMicrophone(64);

        const check = recorder.runMicCheck(5000);
        await new Promise((resolve) => setTimeout(resolve, 250));

        const pill = document.querySelector('#recorder-status-pill');

        const seen = {
            isRecording: recorder.isRecording,
            isProcessing: recorder.isProcessing,
            microphoneBusy: recorder.isMicrophoneBusy(),
            pillVisible: !!pill && pill.offsetParent !== null,
            title: document.title,
        };

        recorder.cancelMicCheck();
        await check;

        return seen;
    JS));

    expect($result['isRecording'])->toBeFalse()
        ->and($result['isProcessing'])->toBeFalse()
        ->and($result['microphoneBusy'])->toBeFalse()
        ->and($result['pillVisible'])->toBeFalse()
        ->and($result['title'])->not->toContain('RECORDING');

    $page->assertNoJavaScriptErrors();
});

it('throws the verdict away when the microphone it described is swapped out', function () {
    $this->actingAs($this->user);

    $result = visit(recorderStep())->script(withMicCheck(<<<'JS'
        fakeMicrophone(64);
        await recorder.runMicCheck(400);
        const before = recorder.micCheckResult;

        await recorder.selectInputDevice('some-other-mic');

        return { before, after: recorder.micCheckResult };
    JS));

    expect($result['before'])->toBe('good')
        ->and($result['after'])->toBeNull();
});

it('says so plainly when the level cannot be measured at all', function () {
    $this->actingAs($this->user);

    /**
     * Web Audio can fail to build, and then there is no analyser to read. The
     * recording still works on the raw stream, but a verdict either way would
     * be invented, and an invented "good" is the worst answer available.
     */
    $page = visit(recorderStep());

    $verdict = $page->script(withMicCheck(<<<'JS'
        recorder.setupStream = async () => {
            recorder.micOpen = true;
            recorder.captureStream = { getTracks: () => [] };
            recorder.analyserNode = null;
            recorder.startWaveform();
        };

        await recorder.runMicCheck(400);

        return recorder.micCheckResult;
    JS));

    expect($verdict)->toBe('unmeasurable');

    $page->assertVisible('#recorder-mic-verdict')
        ->assertSee('would not let us measure the level');
});

it('does not blame the browser when the check collected no frames', function () {
    $this->actingAs($this->user);

    /**
     * Production returned 'unmeasurable' — "this browser would not let us
     * measure the level" — while the analyser was present, the context was
     * running and the stream was live. The browser could measure perfectly
     * well; the meter loop was not running, so nothing was ever read from it.
     * An analyser with no frames means the check did not happen, and that is
     * what the panel has to say.
     */
    $page = visit(recorderStep());

    $verdict = $page->script(withMicCheck(<<<'JS'
        recorder.setupStream = async () => {
            recorder.micOpen = true;
            recorder.captureStream = { getTracks: () => [] };
            recorder.analyserNode = {
                fftSize: 2048,
                getByteTimeDomainData: (buffer) => buffer.fill(128),
            };
        };

        // The meter loop is dead, exactly as it was in production.
        recorder.startWaveform = () => {};

        await recorder.runMicCheck(400);

        return recorder.micCheckResult;
    JS));

    expect($verdict)->toBe('incomplete');

    $page->assertVisible('#recorder-mic-verdict')
        ->assertSee('The check did not finish, so nothing was measured.')
        // Nothing is wrong with the microphone, so the only honest next step
        // is another go at the check.
        ->assertSee('Test again')
        ->assertDontSee('would not let us measure the level');
});

it('starts the meter loop itself rather than trusting the stream setup to have done it', function () {
    $this->actingAs($this->user);

    /**
     * The check is computed from frames the meter loop pushes. When that loop
     * is not running the check judges an empty list, which is how a live
     * microphone came to be reported on as if it had never been heard. Opening
     * the stream is no longer the only thing that can arm the loop.
     */
    $result = visit(recorderStep())->script(withMicCheck(<<<'JS'
        recorder.setupStream = async () => {
            recorder.micOpen = true;
            recorder.captureStream = { getTracks: () => [] };
            recorder.analyserNode = {
                fftSize: 2048,
                getByteTimeDomainData: (buffer) => {
                    for (let i = 0; i < buffer.length; i++) {
                        buffer[i] = 128 + (i % 2 === 0 ? 64 : -64);   // -6 dBFS
                    }
                },
            };
            // Deliberately does not call startWaveform().
        };

        await recorder.runMicCheck(400);

        return recorder.micCheckResult;
    JS));

    expect($result)->toBe('good');
});
