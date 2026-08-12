<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\MomTopic;
use App\Domain\AI\Services\ExtractionService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Owner->value]);

    config(['ai.default' => 'openai']);
    config(['ai.providers.openai.api_key' => 'test-key']);
    config(['ai.providers.openai.model' => 'gpt-4o']);
});

/**
 * extractAll runs five extractions in a fixed order; only the fourth is under
 * test here, so the rest are answered with empty arrays.
 *
 * @param  list<array<string, mixed>>  $topics
 */
function fakeExtractionReturning(array $topics): void
{
    $empty = ['choices' => [['message' => ['content' => '[]']]]];

    Http::fake([
        'api.openai.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['content' => json_encode([
                'summary' => 'A summary.', 'key_points' => '-', 'confidence_score' => 0.9,
            ])]]]])
            ->push($empty)
            ->push($empty)
            ->push(['choices' => [['message' => ['content' => json_encode($topics)]]]])
            ->push($empty),
    ]);
}

function momWithAudio(int $durationSeconds): MinutesOfMeeting
{
    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => test()->org->id,
        'created_by' => test()->user->id,
    ]);

    AudioTranscription::factory()->completed()->create([
        'minutes_of_meeting_id' => $mom->id,
        'uploaded_by' => test()->user->id,
        'duration_seconds' => $durationSeconds,
        'full_text' => 'Everything that was said.',
    ]);

    return $mom;
}

// The reported case: a 2m26s recording came back as five topics totalling
// eighteen minutes, and every one of them was written into the record.
test('durations that could not fit the recording are not stored', function () {
    fakeExtractionReturning([
        ['title' => 'Pengenalan MCP', 'duration_minutes' => 4],
        ['title' => 'Perbandingan langganan', 'duration_minutes' => 3],
        ['title' => 'Contoh penggunaan', 'duration_minutes' => 3],
        ['title' => 'Akaun dan kredit', 'duration_minutes' => 4],
        ['title' => 'Pemasangan penyambung', 'duration_minutes' => 4],
    ]);

    $mom = momWithAudio(146);

    app(ExtractionService::class)->extractAll($mom);

    $topics = MomTopic::query()->where('minutes_of_meeting_id', $mom->id)->get();

    expect($topics)->toHaveCount(5)
        ->and($topics->pluck('title')->all())->toContain('Pengenalan MCP');

    expect($topics->pluck('duration_minutes')->filter()->all())
        ->toBeEmpty('eighteen minutes cannot have happened inside two and a half');
});

test('durations that fit are kept', function () {
    fakeExtractionReturning([
        ['title' => 'Opening', 'duration_minutes' => 12],
        ['title' => 'Budget', 'duration_minutes' => 25],
        ['title' => 'Close', 'duration_minutes' => 8],
    ]);

    $mom = momWithAudio(45 * 60);

    app(ExtractionService::class)->extractAll($mom);

    expect(
        MomTopic::query()->where('minutes_of_meeting_id', $mom->id)
            ->orderBy('sort_order')->pluck('duration_minutes')->all()
    )->toBe([12, 25, 8]);
});

// Topics shorter than a minute are asked for as 0, so a set of them sums to
// less than the recording. That is not a failure and must not throw the rest
// of the set away.
test('a minute of rounding slack does not throw the set away', function () {
    fakeExtractionReturning([
        ['title' => 'One', 'duration_minutes' => 1],
        ['title' => 'Two', 'duration_minutes' => 1],
        ['title' => 'Three', 'duration_minutes' => 0],
    ]);

    $mom = momWithAudio(95);

    app(ExtractionService::class)->extractAll($mom);

    expect(
        MomTopic::query()->where('minutes_of_meeting_id', $mom->id)
            ->orderBy('sort_order')->pluck('duration_minutes')->all()
    )->toBe([1, 1, 0]);
});

// Minutes typed up from notes have no recording to measure against, so any
// number the model offers is a guess that would be displayed as a measurement.
test('a record built without audio stores no durations at all', function () {
    fakeExtractionReturning([
        ['title' => 'Budget', 'duration_minutes' => 20],
    ]);

    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'content' => 'Notes typed up after the meeting.',
    ]);

    app(ExtractionService::class)->extractAll($mom);

    $topic = MomTopic::query()->where('minutes_of_meeting_id', $mom->id)->sole();

    expect($topic->title)->toBe('Budget')
        ->and($topic->duration_minutes)->toBeNull();
});

test('the prompt carries the real length of the recording', function () {
    fakeExtractionReturning([['title' => 'Anything']]);

    app(ExtractionService::class)->extractAll(momWithAudio(146));

    Http::assertSent(function ($request) {
        $body = json_encode($request->data());

        return str_contains((string) $body, '2 minutes 26 seconds');
    });
});
