<?php

declare(strict_types=1);

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Services\TranscriptionHintBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function hintsFor(array $attendees, array $meetingAttributes = []): array
{
    $meeting = MinutesOfMeeting::factory()->create($meetingAttributes);

    foreach ($attendees as $attendee) {
        $meeting->attendees()->create($attendee);
    }

    return app(TranscriptionHintBuilder::class)->keywordsFor($meeting->fresh());
}

it('hints on each part of a name as well as the whole', function (): void {
    $keywords = hintsFor([['name' => 'Noor Ariff']], ['title' => 'Weekly sync']);

    // People get addressed by one part of their name far more often than all of it.
    expect($keywords)->toContain('Noor Ariff', 'Noor', 'Ariff');
});

it('leaves out connectors and honorifics that identify nobody', function (): void {
    $keywords = hintsFor([['name' => 'Dr Aiman bin Hakim']], ['title' => 'Weekly sync']);

    expect($keywords)->toContain('Dr Aiman bin Hakim', 'Aiman', 'Hakim')
        ->and($keywords)->not->toContain('bin')
        ->and($keywords)->not->toContain('Dr');
});

it('keeps organisations and titles whole', function (): void {
    $keywords = hintsFor(
        [['name' => 'Irfan', 'company' => 'Jabatan Perikanan Malaysia']],
        ['title' => 'CR ePengambilan'],
    );

    // Their individual words are ordinary vocabulary, not recognition signal.
    expect($keywords)->toContain('Jabatan Perikanan Malaysia', 'CR ePengambilan')
        ->and($keywords)->not->toContain('Jabatan')
        ->and($keywords)->not->toContain('Perikanan');
});

it('does not repeat a single-word name', function (): void {
    $keywords = hintsFor([['name' => 'Irfan']], ['title' => 'Weekly sync']);

    expect(array_count_values($keywords)['Irfan'])->toBe(1);
});

it('returns nothing without a meeting', function (): void {
    expect(app(TranscriptionHintBuilder::class)->keywordsFor(null))->toBe([]);
});

it('puts the meeting language ahead of the configured fallbacks', function (): void {
    config(['ai.transcription_language_hints' => ['ms', 'en']]);

    $meeting = MinutesOfMeeting::factory()->create(['language' => 'ms']);

    expect(app(TranscriptionHintBuilder::class)->languagesFor($meeting))->toBe(['ms', 'en']);
});
