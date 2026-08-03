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
 * Reach the recorder's Alpine component through the public `Alpine.$data()` API.
 *
 * The component deliberately exposes no debug handle of its own, so the test
 * reads it the same way any other page script would.
 */
function recorderData(string $property): string
{
    return <<<JS
    () => window.Alpine.\$data(document.querySelector('[x-data^="audioRecorder"]')).{$property}
    JS;
}

it('does not open the microphone on page load', function () {
    $this->actingAs($this->user);

    visit(route('meetings.show', $this->meeting))
        ->assertScript(recorderData('micOpen'), false)
        ->assertScript(recorderData('state'), 'idle')
        ->assertNoJavaScriptErrors();
});

it('leaves the microphone closed even when permission was already granted', function () {
    $this->actingAs($this->user);

    /**
     * A headless browser reports microphone permission as "prompt", so the
     * granted branch would never run here. Stub it, count getUserMedia calls,
     * and drive the permission check the way page load drives it.
     */
    $result = visit(route('meetings.show', $this->meeting))->script(<<<'JS'
    async () => {
        const recorder = window.Alpine.$data(document.querySelector('[x-data^="audioRecorder"]'));

        let getUserMediaCalls = 0;
        navigator.mediaDevices.getUserMedia = async () => {
            getUserMediaCalls++;
            throw new Error('the microphone must not be opened without a user gesture');
        };
        navigator.permissions.query = async () => ({ state: 'granted', onchange: null });

        await recorder.checkExistingPermission();

        return {
            getUserMediaCalls,
            micOpen: recorder.micOpen,
            state: recorder.state,
            permissionGranted: recorder.permissionGranted,
        };
    }
    JS);

    expect($result['getUserMediaCalls'])->toBe(0)
        ->and($result['micOpen'])->toBeFalse()
        ->and($result['state'])->toBe('idle')
        ->and($result['permissionGranted'])->toBeTrue();
});
