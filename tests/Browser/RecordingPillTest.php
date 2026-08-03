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
 * Drive the recorder's state the way the component itself would, and hand back
 * what the pill actually renders.
 *
 * The assertions below deliberately read the rendered pill rather than the
 * component's own properties: the property is the input to this feature, not
 * the thing under test.
 */
function pillIn(string $state): string
{
    return <<<JS
    () => {
        window.Alpine.\$data(document.querySelector('[x-data^="audioRecorder"]')).state = '{$state}';

        return true;
    }
    JS;
}

it('shows a worded recording pill only while recording', function () {
    $this->actingAs($this->user);

    $page = visit(route('meetings.show', $this->meeting));

    // An idle recorder must show no pill at all — a permanent badge would be
    // the same false signal this feature exists to remove.
    $page->assertMissing('#recorder-status-pill');

    $page->script(pillIn('recording'));

    // The locale under test is English, so `__('RECORDING')` renders the source
    // string; the Malay bundle carries "MERAKAM" for the same key.
    $page->assertVisible('#recorder-status-pill')
        ->assertSeeIn('#recorder-status-pill', __('RECORDING'))
        ->assertNoJavaScriptErrors();
});

it('words the pill differently while paused, so a stopped clock cannot read as recording', function () {
    $this->actingAs($this->user);

    $page = visit(route('meetings.show', $this->meeting));

    $page->script(pillIn('paused'));

    $page->assertSeeIn('#recorder-status-pill', __('PAUSED'))
        ->assertDontSeeIn('#recorder-status-pill', __('RECORDING'));
});

it('hides the pill again once recording is over', function () {
    $this->actingAs($this->user);

    $page = visit(route('meetings.show', $this->meeting));

    $page->script(pillIn('recording'));
    $page->assertSeeIn('#recorder-status-pill', __('RECORDING'));

    $page->script(pillIn('complete'));
    $page->assertMissing('#recorder-status-pill')
        ->assertDontSee(__('RECORDING'));
});

it('carries the elapsed time in the pill', function () {
    $this->actingAs($this->user);

    $page = visit(route('meetings.show', $this->meeting));

    $page->script(<<<'JS'
    () => {
        const recorder = window.Alpine.$data(document.querySelector('[x-data^="audioRecorder"]'));
        recorder.state = 'recording';
        recorder.timer = 754;

        return true;
    }
    JS);

    $page->assertSeeIn('#recorder-status-pill', '12:34');
});

it('beats the dot on the timer tick rather than on a CSS animation of its own', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(<<<'JS'
    async () => {
        const recorder = window.Alpine.$data(document.querySelector('[x-data^="audioRecorder"]'));
        recorder.state = 'recording';

        const opacityNow = () => {
            const dot = document.querySelector('#recorder-status-pill span');

            return getComputedStyle(dot).opacity;
        };

        const settle = () => new Promise((resolve) => setTimeout(resolve, 400));

        recorder.pulseOn = true;
        await settle();
        const lit = opacityNow();

        recorder.pulseOn = false;
        await settle();
        const dim = opacityNow();

        // The tick is what moves it: nothing else may.
        recorder.startTimerTick();
        const before = recorder.pulseOn;
        await new Promise((resolve) => setTimeout(resolve, 1100));
        const after = recorder.pulseOn;
        const timer = recorder.timer;
        recorder.cleanup();

        return { lit, dim, flipped: before !== after, timer };
    }
    JS);

    expect($result['lit'])->toBe('1')
        ->and($result['dim'])->not->toBe('1')
        ->and($result['flipped'])->toBeTrue()
        ->and($result['timer'])->toBe(1);
});

it('keeps the pill clear of the mobile bottom navigation', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->on()->mobile()->script(<<<'JS'
    async () => {
        window.Alpine.$data(document.querySelector('[x-data^="audioRecorder"]')).state = 'recording';

        await new Promise((resolve) => setTimeout(resolve, 200));

        const pill = document.querySelector('#recorder-status-pill').getBoundingClientRect();
        const nav = document.querySelector('nav.fixed.bottom-0').getBoundingClientRect();

        return {
            pillBottom: pill.bottom,
            navTop: nav.top,
            pillZ: getComputedStyle(document.querySelector('#recorder-status-pill')).zIndex,
            navZ: getComputedStyle(document.querySelector('nav.fixed.bottom-0')).zIndex,
        };
    }
    JS);

    expect($result['pillBottom'])->toBeLessThanOrEqual($result['navTop'])
        ->and((int) $result['pillZ'])->toBeLessThan((int) $result['navZ']);
});
