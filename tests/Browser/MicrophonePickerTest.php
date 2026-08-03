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
 * Stub `enumerateDevices` with a fixed list and let the component read it the
 * same way setupStream() does. Headless Chromium exposes no real microphone, so
 * the device list is the one thing a test has to supply.
 *
 * A camera is always included: the picker must never offer it.
 *
 * The wizard opens on step 1, which leaves the whole recorder panel behind a
 * `display: none` ancestor — every visibility assertion would pass for the
 * wrong reason. Open step 3 first so "not visible" means the picker's own
 * x-show, and nothing else.
 */
function stubbedInputDevices(int $microphones): string
{
    return <<<JS
    async () => {
        const devices = [{ kind: 'videoinput', deviceId: 'cam-0', label: 'FaceTime Camera' }];
        for (let i = 0; i < {$microphones}; i++) {
            devices.push({ kind: 'audioinput', deviceId: 'mic-' + i, label: 'Microphone ' + i });
        }

        navigator.mediaDevices.enumerateDevices = async () => devices;

        const recorder = window.Alpine.\$data(document.querySelector('[x-data^="audioRecorder"]'));
        recorder.activeStep = 3;

        await recorder.loadInputDevices();

        return true;
    }
    JS;
}

/**
 * Run an async function with the recorder component bound to `recorder`.
 */
function inRecorder(string $body): string
{
    return <<<JS
    async () => {
        const recorder = window.Alpine.\$data(document.querySelector('[x-data^="audioRecorder"]'));
        {$body}
    }
    JS;
}

it('hides the picker when there is no choice to make', function () {
    $this->actingAs($this->user);

    $page = visit(route('meetings.show', $this->meeting));

    $page->script(stubbedInputDevices(1));

    // The recorder itself must be on screen, or "missing" proves nothing.
    $page->assertVisible('[x-data^="audioRecorder"]')
        ->assertMissing('#recorder-mic-device')
        ->assertScript(inRecorder('return recorder.inputDevices.length;'), 1);
});

it('offers the picker once a second microphone is available', function () {
    $this->actingAs($this->user);

    $page = visit(route('meetings.show', $this->meeting));

    $page->script(stubbedInputDevices(2));

    // The camera is filtered out, and each option carries the device's own id,
    // which is what selectInputDevice() is handed on change.
    $page->assertVisible('#recorder-mic-device')
        ->assertCount('#recorder-mic-device option', 2)
        ->assertScript(
            "[...document.querySelectorAll('#recorder-mic-device option')].map((o) => o.value + ':' + o.textContent).join('|')",
            'mic-0:Microphone 0|mic-1:Microphone 1'
        );
});

it('refuses to swap the microphone out from under a running recording', function () {
    $this->actingAs($this->user);

    /**
     * Reopening the stream mid-recording hands MediaRecorder a dead track and
     * ends the capture silently, so the guard has to hold even when the click
     * gets through — the disabled attribute alone is not a safety mechanism.
     */
    $result = visit(route('meetings.show', $this->meeting))->script(inRecorder(<<<'JS'
        let releases = 0;
        recorder.releaseStream = () => { releases++; };
        recorder.state = 'recording';

        await recorder.selectInputDevice('mic-1');

        return {
            releases,
            selectedDeviceId: recorder.selectedDeviceId,
            state: recorder.state,
            stored: localStorage.getItem('antaranote-mic-device'),
        };
    JS));

    expect($result['releases'])->toBe(0)
        ->and($result['selectedDeviceId'])->toBeNull()
        ->and($result['state'])->toBe('recording')
        ->and($result['stored'])->toBeNull();
});

it('disables the picker while a recording is in flight', function () {
    $this->actingAs($this->user);

    $page = visit(route('meetings.show', $this->meeting));

    $page->script(stubbedInputDevices(2));
    $page->assertEnabled('#recorder-mic-device');

    $page->script(inRecorder("recorder.state = 'recording'; return true;"));
    $page->assertDisabled('#recorder-mic-device');
});

it('falls back to the default microphone when the remembered one is gone', function () {
    $this->actingAs($this->user);

    /**
     * The USB conference mic from last week's meeting is unplugged. Asking for
     * it by exact id rejects, and without a fallback the user cannot record at
     * all — with an error naming a device they never chose today.
     */
    $result = visit(route('meetings.show', $this->meeting))->script(inRecorder(<<<'JS'
        const requested = [];
        navigator.mediaDevices.getUserMedia = async (constraints) => {
            requested.push(constraints.audio.deviceId ? constraints.audio.deviceId.exact : null);

            if (constraints.audio.deviceId) {
                const error = new Error('device is gone');
                error.name = 'OverconstrainedError';
                throw error;
            }

            return { getTracks: () => [] };
        };
        navigator.mediaDevices.enumerateDevices = async () => [];

        localStorage.setItem('antaranote-mic-device', 'unplugged-usb-mic');
        recorder.selectedDeviceId = 'unplugged-usb-mic';

        await recorder.requestPermission();

        return {
            requested,
            state: recorder.state,
            micOpen: recorder.micOpen,
            errorMessage: recorder.errorMessage,
            selectedDeviceId: recorder.selectedDeviceId,
            stored: localStorage.getItem('antaranote-mic-device'),
        };
    JS));

    expect($result['requested'])->toBe(['unplugged-usb-mic', null])
        ->and($result['state'])->toBe('ready')
        ->and($result['micOpen'])->toBeTrue()
        ->and($result['errorMessage'])->toBe('')
        ->and($result['selectedDeviceId'])->toBeNull()
        ->and($result['stored'])->toBeNull();
});

it('does not retry past a denied permission', function () {
    $this->actingAs($this->user);

    /**
     * The fallback exists for a device that vanished, not for a user who said
     * no. Retrying a denial would ask twice and report the wrong error.
     */
    $result = visit(route('meetings.show', $this->meeting))->script(inRecorder(<<<'JS'
        let calls = 0;
        navigator.mediaDevices.getUserMedia = async () => {
            calls++;
            const error = new Error('denied');
            error.name = 'NotAllowedError';
            throw error;
        };

        recorder.selectedDeviceId = 'mic-1';

        await recorder.requestPermission();

        return { calls, state: recorder.state, errorMessage: recorder.errorMessage };
    JS));

    expect($result['calls'])->toBe(1)
        ->and($result['state'])->toBe('error')
        ->and($result['errorMessage'])->toContain('denied');
});
