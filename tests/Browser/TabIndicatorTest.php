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

it('marks the tab title while recording', function () {
    $this->actingAs($this->user);

    $page = visit(route('meetings.show', $this->meeting));

    $page->script(<<<'JS'
    () => {
        const recorder = window.Alpine.$data(document.querySelector('[x-data^="audioRecorder"]'));
        recorder.state = 'recording';
        recorder.timer = 754;
        recorder.syncTabIndicator();

        return true;
    }
    JS);

    $page->assertTitleContains('●')
        ->assertTitleContains('12:34')
        ->assertTitleContains(__('RECORDING'));
});

it('restores the tab title and favicon once recording stops', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(<<<'JS'
    () => {
        const recorder = window.Alpine.$data(document.querySelector('[x-data^="audioRecorder"]'));
        const favicon = () => document.querySelector('link[rel="icon"]').getAttribute('href');

        const originalTitle = document.title;
        const originalFavicon = favicon();

        recorder.state = 'recording';
        recorder.syncTabIndicator();

        const recordingTitle = document.title;
        const recordingFavicon = favicon();

        recorder.state = 'complete';
        recorder.syncTabIndicator();

        return {
            originalTitle,
            originalFavicon,
            recordingTitle,
            recordingFavicon,
            restoredTitle: document.title,
            restoredFavicon: favicon(),
        };
    }
    JS);

    // A leaked ● after stopping would be its own confusion bug.
    expect($result['recordingTitle'])->not->toBe($result['originalTitle'])
        ->and($result['recordingFavicon'])->toStartWith('data:image/png')
        ->and($result['restoredTitle'])->toBe($result['originalTitle'])
        ->and($result['restoredFavicon'])->toBe($result['originalFavicon']);
});

it('restores the tab when the component is torn down mid-recording', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(<<<'JS'
    () => {
        const recorder = window.Alpine.$data(document.querySelector('[x-data^="audioRecorder"]'));
        const favicon = () => document.querySelector('link[rel="icon"]').getAttribute('href');

        const originalTitle = document.title;
        const originalFavicon = favicon();

        recorder.state = 'recording';
        recorder.syncTabIndicator();

        recorder.cleanup();

        return {
            originalTitle,
            originalFavicon,
            restoredTitle: document.title,
            restoredFavicon: favicon(),
        };
    }
    JS);

    expect($result['restoredTitle'])->toBe($result['originalTitle'])
        ->and($result['restoredFavicon'])->toBe($result['originalFavicon']);
});

it('follows every state transition without being called by hand', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(<<<'JS'
    async () => {
        const recorder = window.Alpine.$data(document.querySelector('[x-data^="audioRecorder"]'));
        const settle = () => new Promise((resolve) => setTimeout(resolve, 200));

        const originalTitle = document.title;

        recorder.state = 'recording';
        await settle();
        const marked = document.title;

        recorder.state = 'paused';
        await settle();

        return { originalTitle, marked, whilePaused: document.title };
    }
    JS);

    expect($result['marked'])->toContain('●')
        // Paused is not recording: the tab must stop claiming it is.
        ->and($result['whilePaused'])->toBe($result['originalTitle']);
});

it('keeps the tab title advancing with the timer tick', function () {
    $this->actingAs($this->user);

    $result = visit(route('meetings.show', $this->meeting))->script(<<<'JS'
    async () => {
        const recorder = window.Alpine.$data(document.querySelector('[x-data^="audioRecorder"]'));

        recorder.state = 'recording';
        recorder.timer = 0;
        recorder.syncTabIndicator();

        const first = document.title;

        recorder.startTimerTick();
        await new Promise((resolve) => setTimeout(resolve, 1100));

        const second = document.title;
        recorder.cleanup();

        return { first, second };
    }
    JS);

    expect($result['first'])->toContain('0:00')
        ->and($result['second'])->toContain('0:01');
});
