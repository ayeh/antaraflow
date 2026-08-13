<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Services\AiCircuitBreaker;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Jobs\DiarizeTranscriptionJob;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Models\TranscriptionSegment;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);
    $this->transcription = AudioTranscription::factory()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'uploaded_by' => $this->user->id,
    ]);
});

/**
 * Binds a provider that answers with a fixed JSON map.
 *
 * Nothing in this file may reach a real provider: diarization is an LLM call
 * and a test suite that makes one is a test suite that costs money and fails
 * when a key rotates.
 */
function fakeDiarizer(?string $response, ?callable $onCall = null): void
{
    $provider = Mockery::mock(AIProviderInterface::class);
    $provider->shouldReceive('chat')->andReturnUsing(function (...$arguments) use ($response, $onCall) {
        if ($onCall !== null) {
            $onCall(...$arguments);
        }

        return $response;
    });

    app()->instance(AIProviderInterface::class, $provider);
}

function segmentFor(AudioTranscription $transcription, string $text, int $order, bool $edited = false): TranscriptionSegment
{
    return TranscriptionSegment::query()->create([
        'audio_transcription_id' => $transcription->id,
        'text' => $text,
        'speaker' => null,
        'start_time' => $order * 5.0,
        'end_time' => ($order + 1) * 5.0,
        'confidence' => 0.9,
        'sequence_order' => $order,
        'is_edited' => $edited,
    ]);
}

test('names the segments from the attendees who were present', function () {
    MomAttendee::query()->create([
        'minutes_of_meeting_id' => $this->meeting->id,
        'name' => 'Aminah',
        'is_present' => true,
    ]);

    $first = segmentFor($this->transcription, 'I move that we approve the budget', 0);
    $second = segmentFor($this->transcription, 'seconded', 1);

    fakeDiarizer(json_encode([
        (string) $first->id => 'Aminah',
        (string) $second->id => 'Speaker 2',
    ]));

    (new DiarizeTranscriptionJob($this->transcription))->handle();

    expect($first->fresh()->speaker)->toBe('Aminah')
        ->and($second->fresh()->speaker)->toBe('Speaker 2');
});

test('leaves a segment somebody has corrected by hand alone', function () {
    $segment = segmentFor($this->transcription, 'the chair opened the meeting', 0, edited: true);

    fakeDiarizer(json_encode([(string) $segment->id => 'Somebody Else']));

    (new DiarizeTranscriptionJob($this->transcription))->handle();

    expect($segment->fresh()->speaker)->toBeNull();
});

test('does not call the provider at all when there is nothing to label', function () {
    $called = false;
    fakeDiarizer('{}', function () use (&$called) {
        $called = true;
    });

    (new DiarizeTranscriptionJob($this->transcription))->handle();

    expect($called)->toBeFalse();
});

// Diarization sits on top of a transcript that already exists and is already
// useful. Nothing it can do is worth failing a recorded meeting over.
test('gives up quietly when the provider answers with nonsense', function () {
    $segment = segmentFor($this->transcription, 'anything', 0);

    fakeDiarizer('I am afraid I cannot help with that.');

    (new DiarizeTranscriptionJob($this->transcription))->handle();

    expect($segment->fresh()->speaker)->toBeNull();
});

test('gives up quietly when the provider throws', function () {
    $segment = segmentFor($this->transcription, 'anything', 0);

    $provider = Mockery::mock(AIProviderInterface::class);
    $provider->shouldReceive('chat')->andThrow(new RuntimeException('provider down'));
    app()->instance(AIProviderInterface::class, $provider);

    (new DiarizeTranscriptionJob($this->transcription))->handle();

    expect($segment->fresh()->speaker)->toBeNull();
});

test('does not call the provider while the circuit is open', function () {
    segmentFor($this->transcription, 'anything', 0);

    $called = false;
    fakeDiarizer('{}', function () use (&$called) {
        $called = true;
    });

    app(AiCircuitBreaker::class)->trip(DiarizeTranscriptionJob::CIRCUIT);

    (new DiarizeTranscriptionJob($this->transcription))->handle();

    expect($called)->toBeFalse();
});
