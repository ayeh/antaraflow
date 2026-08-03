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
 * Stub everything the recorder touches that headless Chromium cannot provide:
 * a wake lock that counts requests and releases, a MediaRecorder that never
 * encodes anything, and a capture stream with no tracks.
 *
 * Recording is then started and stopped through the component's own entry
 * points, so what is under test is the wiring, not the two helpers in
 * isolation.
 */
function wakeLockHarness(string $body): string
{
    return <<<JS
    async () => {
        const recorder = window.Alpine.\$data(document.querySelector('[x-data^="audioRecorder"]'));
        const settle = () => new Promise((resolve) => setTimeout(resolve, 50));

        const calls = { requests: 0, releases: 0 };
        const autoReleaseHandlers = [];

        Object.defineProperty(navigator, 'wakeLock', {
            configurable: true,
            value: {
                request: async () => {
                    calls.requests++;

                    return {
                        addEventListener: (name, handler) => {
                            if (name === 'release') {
                                autoReleaseHandlers.push(handler);
                            }
                        },
                        release: async () => {
                            calls.releases++;
                        },
                    };
                },
            },
        });

        window.MediaRecorder = class {
            constructor() {
                this.state = 'recording';
            }

            start() {}

            stop() {
                this.state = 'inactive';
                this.onstop?.();
            }
        };

        recorder.captureStream = { getTracks: () => [] };
        recorder.state = 'ready';

        {$body}
    }
    JS;
}

it('takes a screen wake lock when recording begins and gives it back when it stops', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(wakeLockHarness(<<<'JS'
    recorder.beginRecording();
    await settle();

    const afterBegin = { ...calls };

    await recorder.stopRecording();
    await settle();

    const afterStop = { ...calls };

    recorder.cleanup();

    return { afterBegin, afterStop };
    JS));

    expect($result['afterBegin']['requests'])->toBe(1)
        ->and($result['afterBegin']['releases'])->toBe(0)
        ->and($result['afterStop']['releases'])->toBe(1);
});

it('takes a fresh lock after the browser drops the one it held for a hidden tab', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(wakeLockHarness(<<<'JS'
    recorder.beginRecording();
    await settle();

    const afterBegin = calls.requests;

    // What the browser does on its own when the tab is hidden.
    autoReleaseHandlers.forEach((handler) => handler());

    document.dispatchEvent(new Event('visibilitychange'));
    await settle();

    const afterReturning = calls.requests;

    recorder.cleanup();

    return { afterBegin, afterReturning };
    JS));

    expect($result['afterBegin'])->toBe(1)
        // A spent sentinel left in place would block this for the rest of the
        // meeting, and the screen would go dark mid-recording.
        ->and($result['afterReturning'])->toBe(2);
});

it('gives the lock back when the component is torn down mid-recording', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(wakeLockHarness(<<<'JS'
    recorder.beginRecording();
    await settle();

    recorder.cleanup();
    await settle();

    return { releases: calls.releases };
    JS));

    expect($result['releases'])->toBe(1);
});

it('does not stack locks when the tab is shown again while one is still held', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(wakeLockHarness(<<<'JS'
    recorder.beginRecording();
    await settle();

    document.dispatchEvent(new Event('visibilitychange'));
    document.dispatchEvent(new Event('visibilitychange'));
    await settle();

    const requests = calls.requests;

    recorder.cleanup();

    return { requests };
    JS));

    expect($result['requests'])->toBe(1);
});

it('does not hold the screen awake once recording is over', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(wakeLockHarness(<<<'JS'
    recorder.beginRecording();
    await settle();

    await recorder.stopRecording();
    await settle();

    const afterStop = calls.requests;

    document.dispatchEvent(new Event('visibilitychange'));
    await settle();

    const afterReturning = calls.requests;

    recorder.cleanup();

    return { afterStop, afterReturning };
    JS));

    expect($result['afterReturning'])->toBe($result['afterStop']);
});

it('records happily when the browser has no wake lock at all', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(<<<'JS'
    async () => {
        const recorder = window.Alpine.$data(document.querySelector('[x-data^="audioRecorder"]'));

        Object.defineProperty(navigator, 'wakeLock', { configurable: true, value: undefined });

        window.MediaRecorder = class {
            constructor() { this.state = 'recording'; }
            start() {}
            stop() { this.state = 'inactive'; this.onstop?.(); }
        };

        recorder.captureStream = { getTracks: () => [] };
        recorder.state = 'ready';
        recorder.beginRecording();

        await new Promise((resolve) => setTimeout(resolve, 50));

        const state = recorder.state;

        recorder.releaseWakeLock();
        recorder.cleanup();

        return { state };
    }
    JS);

    expect($result['state'])->toBe('recording');
});
