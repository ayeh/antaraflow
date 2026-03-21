# Phase 1 MVP Gaps Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Complete B2 (browser recording language UI), B4 (speaker diarization with manual rename + timeline), B5 (multi-language selector), and E2 (cross-meeting AI search with streaming + citations).

**Architecture:** Language selector is a reusable Blade component shared by the recorder and upload form. Speaker diarization uses a heuristic time-gap algorithm inside the existing job, with a new SpeakerController for bulk rename. E2 extends the existing `/search` page with an AI tab backed by a new AiSearchService that assembles meeting context and streams responses via SSE.

**Tech Stack:** Laravel 12, Alpine.js, Blade components, Server-Sent Events (SSE), Laravel Cache, OpenAI Whisper, existing AIProviderFactory

---

## Task 1: Language Selector Blade Component

**Files:**
- Create: `resources/views/components/language-select.blade.php`

**Step 1: Create the component**

```blade
@props(['name' => 'language', 'selected' => 'en'])

@php
$languages = [
    'en' => 'English',
    'ms' => 'Bahasa Melayu',
    'zh' => 'Chinese (中文)',
    'ta' => 'Tamil (தமிழ்)',
    'ja' => 'Japanese (日本語)',
    'ko' => 'Korean (한국어)',
    'fr' => 'French (Français)',
    'de' => 'German (Deutsch)',
    'es' => 'Spanish (Español)',
    'pt' => 'Portuguese (Português)',
    'ar' => 'Arabic (العربية)',
    'hi' => 'Hindi (हिन्दी)',
];
@endphp

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        {{ __('Audio Language') }}
    </label>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500']) }}
    >
        @foreach($languages as $code => $label)
            <option value="{{ $code }}" @selected($selected === $code)>{{ $label }}</option>
        @endforeach
    </select>
</div>
```

**Step 2: Write failing test**

Create `tests/Feature/Domain/Transcription/Components/LanguageSelectComponentTest.php`:

```php
<?php

use function Pest\Laravel\get;

it('renders language select component with default english selected', function (): void {
    $view = $this->blade('<x-language-select />');

    $view->assertSee('English');
    $view->assertSee('Bahasa Melayu');
    $view->assertSee('selected', false);
});

it('renders with specified language pre-selected', function (): void {
    $view = $this->blade('<x-language-select selected="ms" />');

    $view->assertSee('value="ms" selected', false);
});

it('renders with custom name attribute', function (): void {
    $view = $this->blade('<x-language-select name="audio_language" />');

    $view->assertSee('name="audio_language"', false);
});
```

**Step 3: Run test to verify it fails**

```bash
php artisan test --compact --filter=LanguageSelectComponentTest
```
Expected: FAIL — component not found.

**Step 4: Run test to verify it passes after creating component**

```bash
php artisan test --compact --filter=LanguageSelectComponentTest
```
Expected: 3 passed.

**Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 6: Commit**

```bash
git add resources/views/components/language-select.blade.php tests/Feature/Domain/Transcription/Components/LanguageSelectComponentTest.php
git commit -m "feat: add reusable language selector Blade component"
```

---

## Task 2: Language Selector in Upload Form (B5)

**Files:**
- Modify: `resources/views/meetings/tabs/transcription.blade.php`

**Step 1: Write failing test**

Add to `tests/Feature/Domain/Transcription/Controllers/TranscriptionControllerTest.php` or create a new view test:

```php
it('transcription upload form contains language selector', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->for($org, 'organization')->create();
    $meeting = MinutesOfMeeting::factory()->for($org)->create();

    $response = $this->actingAs($user)->get(route('meetings.show', $meeting));

    $response->assertSee('name="language"', false);
    $response->assertSee('value="en"', false);
    $response->assertSee('value="ms"', false);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter="transcription upload form contains language selector"
```
Expected: FAIL.

**Step 3: Add language selector to upload form**

In `resources/views/meetings/tabs/transcription.blade.php`, replace the existing `<form>` contents:

Find:
```blade
        <div class="flex items-end gap-4">
            <div class="flex-1">
                <label for="audio_file" class="block text-sm font-medium text-gray-700 mb-1">Upload Audio File</label>
                <input type="file" name="audio_file" id="audio_file" accept="audio/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors whitespace-nowrap">Upload</button>
        </div>
```

Replace with:
```blade
        <div class="space-y-3">
            <div class="flex items-end gap-4">
                <div class="flex-1">
                    <label for="audio_file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Upload Audio File</label>
                    <input type="file" name="audio" id="audio_file" accept="audio/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors whitespace-nowrap">Upload</button>
            </div>
            <x-language-select name="language" :selected="old('language', 'en')" />
        </div>
```

**Step 4: Run test to verify it passes**

```bash
php artisan test --compact --filter="transcription upload form contains language selector"
```
Expected: PASS.

**Step 5: Commit**

```bash
git add resources/views/meetings/tabs/transcription.blade.php
git commit -m "feat: add language selector to audio upload form (B5)"
```

---

## Task 3: Language Selector in Browser Recorder (B2)

**Files:**
- Modify: `resources/js/audio-recorder.js`

**Step 1: Add `language` state to Alpine data**

Find the section where state properties are initialized (around line 10-50, where `state`, `sessionId`, `mimeType`, etc. are defined). Add:

```javascript
language: navigator.language ? navigator.language.split('-')[0] : 'en',
```

Place it alongside other state properties.

**Step 2: Add language dropdown in the `ready` state UI**

Find the Blade file that renders the `x-audio-recorder` component or where the recorder Alpine component HTML is defined. This is likely in `resources/views/meetings/tabs/transcription.blade.php` or a dedicated component.

Look for where the recorder's "ready" state is displayed (the recording controls). Add the language selector:

```html
<template x-if="state === 'ready' || state === 'countdown' || state === 'recording'">
    <div class="mt-3">
        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Recording Language</label>
        <select x-model="language" x-bind:disabled="state !== 'ready'"
            class="block w-full rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50">
            <option value="en">English</option>
            <option value="ms">Bahasa Melayu</option>
            <option value="zh">Chinese (中文)</option>
            <option value="ta">Tamil (தமிழ்)</option>
            <option value="ja">Japanese (日本語)</option>
            <option value="ko">Korean (한국어)</option>
            <option value="fr">French (Français)</option>
            <option value="de">German (Deutsch)</option>
            <option value="es">Spanish (Español)</option>
            <option value="pt">Portuguese (Português)</option>
            <option value="ar">Arabic (العربية)</option>
            <option value="hi">Hindi (हिन्दी)</option>
        </select>
    </div>
</template>
```

**Step 3: Replace hardcoded language in `finalizeChunkedUpload`**

In `resources/js/audio-recorder.js` at line 536, replace:

```javascript
language: 'en',
```

With:

```javascript
language: this.language,
```

**Step 4: Also pass language in `uploadSingleFile` if it exists**

Find any other place where audio is uploaded (around line 555+) and ensure `language: this.language` is included in the form data or request body.

**Step 5: Commit**

```bash
git add resources/js/audio-recorder.js
git commit -m "feat: add dynamic language selection to browser recorder (B2/B5)"
```

---

## Task 4: Speaker Auto-grouping in ProcessTranscriptionJob (B4)

**Files:**
- Modify: `app/Domain/Transcription/Jobs/ProcessTranscriptionJob.php`
- Test: `tests/Feature/Domain/Transcription/Jobs/ProcessTranscriptionJobTest.php`

**Step 1: Write failing test**

Add to `tests/Feature/Domain/Transcription/Jobs/ProcessTranscriptionJobTest.php`:

```php
it('assigns speaker labels based on time gap heuristic', function (): void {
    // Arrange: 3 segments — gap > 1.5s between segments 1-2 and 2-3
    $segments = [
        new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
            text: 'Hello', speaker: null, startTime: 0.0, endTime: 2.0, confidence: 0.9
        ),
        new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
            text: 'How are you', speaker: null, startTime: 4.0, endTime: 6.0, confidence: 0.9
        ),
        new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
            text: 'I am fine', speaker: null, startTime: 8.0, endTime: 10.0, confidence: 0.9
        ),
    ];

    $job = new \App\Domain\Transcription\Jobs\ProcessTranscriptionJob(
        \App\Domain\Transcription\Models\AudioTranscription::factory()->make()
    );

    $result = $job->assignSpeakers($segments);

    expect($result[0]->speaker)->toBe('Speaker 1');
    expect($result[1]->speaker)->toBe('Speaker 2');
    expect($result[2]->speaker)->toBe('Speaker 3');
});

it('keeps same speaker when gap is less than threshold', function (): void {
    $segments = [
        new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
            text: 'Hello', speaker: null, startTime: 0.0, endTime: 2.0, confidence: 0.9
        ),
        new \App\Infrastructure\AI\DTOs\TranscriptionSegmentData(
            text: 'World', speaker: null, startTime: 2.5, endTime: 4.0, confidence: 0.9
        ),
    ];

    $job = new \App\Domain\Transcription\Jobs\ProcessTranscriptionJob(
        \App\Domain\Transcription\Models\AudioTranscription::factory()->make()
    );

    $result = $job->assignSpeakers($segments);

    expect($result[0]->speaker)->toBe('Speaker 1');
    expect($result[1]->speaker)->toBe('Speaker 1');
});
```

**Step 2: Run tests to verify they fail**

```bash
php artisan test --compact --filter="assigns speaker labels|keeps same speaker"
```
Expected: FAIL — `assignSpeakers` method does not exist.

**Step 3: Add `assignSpeakers` method to ProcessTranscriptionJob**

Add this public method to `ProcessTranscriptionJob`:

```php
/**
 * Assign speaker labels using time-gap heuristic.
 * A gap of more than $gapThreshold seconds between segments indicates a new speaker.
 *
 * @param  array<\App\Infrastructure\AI\DTOs\TranscriptionSegmentData>  $segments
 * @return array<\App\Infrastructure\AI\DTOs\TranscriptionSegmentData>
 */
public function assignSpeakers(array $segments, float $gapThreshold = 1.5): array
{
    $speakerIndex = 1;
    $previousEndTime = null;

    foreach ($segments as $segment) {
        if ($previousEndTime !== null && ($segment->startTime - $previousEndTime) > $gapThreshold) {
            $speakerIndex++;
        }

        $segment->speaker = 'Speaker '.$speakerIndex;
        $previousEndTime = $segment->endTime;
    }

    return $segments;
}
```

**Step 4: Call `assignSpeakers` inside `handle()` before saving segments**

In the `handle()` method, find:

```php
foreach ($result->segments as $i => $segment) {
```

Before that loop, add:

```php
$assignedSegments = $this->assignSpeakers($result->segments);
```

Then change:

```php
foreach ($result->segments as $i => $segment) {
```

To:

```php
foreach ($assignedSegments as $i => $segment) {
```

**Step 5: Run tests to verify they pass**

```bash
php artisan test --compact --filter="assigns speaker labels|keeps same speaker"
```
Expected: PASS.

**Step 6: Run all transcription tests to confirm no regression**

```bash
php artisan test --compact --filter=Transcription
```
Expected: All pass.

**Step 7: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 8: Commit**

```bash
git add app/Domain/Transcription/Jobs/ProcessTranscriptionJob.php tests/Feature/Domain/Transcription/Jobs/ProcessTranscriptionJobTest.php
git commit -m "feat: auto-assign speaker labels using time-gap heuristic (B4)"
```

---

## Task 5: SpeakerController — Bulk Rename (B4)

**Files:**
- Create: `app/Domain/Transcription/Controllers/SpeakerController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/Transcription/Controllers/SpeakerControllerTest.php`

**Step 1: Create the controller**

```bash
php artisan make:class app/Domain/Transcription/Controllers/SpeakerController --no-interaction
```

**Step 2: Write failing test**

Create `tests/Feature/Domain/Transcription/Controllers/SpeakerControllerTest.php`:

```php
<?php

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use App\Domain\Transcription\Models\TranscriptionSegment;
use App\Models\Organization;
use App\Models\User;

it('renames all matching speaker labels in a transcription', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->for($org, 'organization')->create();
    $meeting = MinutesOfMeeting::factory()->for($org)->create();
    $transcription = AudioTranscription::factory()->for($meeting, 'meeting')->create();

    TranscriptionSegment::factory()->for($transcription)->create(['speaker' => 'Speaker 1', 'sequence_order' => 0]);
    TranscriptionSegment::factory()->for($transcription)->create(['speaker' => 'Speaker 1', 'sequence_order' => 1]);
    TranscriptionSegment::factory()->for($transcription)->create(['speaker' => 'Speaker 2', 'sequence_order' => 2]);

    $response = $this->actingAs($user)->patch(
        route('meetings.transcriptions.speakers.update', [$meeting, $transcription]),
        ['old_speaker' => 'Speaker 1', 'new_speaker' => 'Ahmad']
    );

    $response->assertOk();
    expect(TranscriptionSegment::where('speaker', 'Ahmad')->count())->toBe(2);
    expect(TranscriptionSegment::where('speaker', 'Speaker 2')->count())->toBe(1);
});

it('returns 422 when old_speaker is missing', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->for($org, 'organization')->create();
    $meeting = MinutesOfMeeting::factory()->for($org)->create();
    $transcription = AudioTranscription::factory()->for($meeting, 'meeting')->create();

    $this->actingAs($user)
        ->patchJson(route('meetings.transcriptions.speakers.update', [$meeting, $transcription]), [])
        ->assertUnprocessable();
});
```

**Step 3: Run tests to verify they fail**

```bash
php artisan test --compact --filter=SpeakerControllerTest
```
Expected: FAIL — route does not exist.

**Step 4: Implement SpeakerController**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Transcription\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Transcription\Models\AudioTranscription;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SpeakerController extends Controller
{
    use AuthorizesRequests;

    public function update(Request $request, MinutesOfMeeting $meeting, AudioTranscription $transcription): JsonResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'old_speaker' => ['required', 'string', 'max:100'],
            'new_speaker' => ['required', 'string', 'max:100'],
        ]);

        $transcription->segments()
            ->where('speaker', $validated['old_speaker'])
            ->update(['speaker' => $validated['new_speaker'], 'is_edited' => true]);

        return response()->json(['message' => 'Speaker renamed successfully.']);
    }
}
```

**Step 5: Add route to `routes/web.php`**

Inside the `Route::prefix('meetings/{meeting}')->as('meetings.')->group(function () {` block, after the existing `transcriptions` resource route, add:

```php
Route::patch('transcriptions/{transcription}/speakers', [\App\Domain\Transcription\Controllers\SpeakerController::class, 'update'])->name('transcriptions.speakers.update');
```

**Step 6: Run tests to verify they pass**

```bash
php artisan test --compact --filter=SpeakerControllerTest
```
Expected: PASS.

**Step 7: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 8: Commit**

```bash
git add app/Domain/Transcription/Controllers/SpeakerController.php routes/web.php tests/Feature/Domain/Transcription/Controllers/SpeakerControllerTest.php
git commit -m "feat: add SpeakerController for bulk speaker rename (B4)"
```

---

## Task 6: Speaker Timeline + Inline Rename UI (B4)

**Files:**
- Modify: `resources/views/transcriptions/show.blade.php`

**Step 1: Write failing view test**

```php
it('shows speaker timeline when segments have speakers', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->for($org, 'organization')->create();
    $meeting = MinutesOfMeeting::factory()->for($org)->create();
    $transcription = AudioTranscription::factory()
        ->for($meeting, 'meeting')
        ->create(['duration_seconds' => 100, 'status' => 'completed']);

    TranscriptionSegment::factory()->for($transcription)->create([
        'speaker' => 'Speaker 1', 'start_time' => 0, 'end_time' => 40, 'sequence_order' => 0,
    ]);
    TranscriptionSegment::factory()->for($transcription)->create([
        'speaker' => 'Speaker 2', 'start_time' => 42, 'end_time' => 100, 'sequence_order' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('meetings.transcriptions.show', [$meeting, $transcription]))
        ->assertSee('Speaker 1')
        ->assertSee('Speaker 2')
        ->assertSee('speaker-timeline', false);
});
```

**Step 2: Run test to verify it fails**

```bash
php artisan test --compact --filter="shows speaker timeline"
```
Expected: FAIL.

**Step 3: Add speaker timeline to `transcriptions/show.blade.php`**

After the `<div class="flex items-center justify-between">` header section and before the `@if($transcription->full_text)` block, insert:

```blade
{{-- Speaker Timeline --}}
@if($transcription->segments->whereNotNull('speaker')->isNotEmpty() && $transcription->duration_seconds)
    @php
        $timelineColors = [
            'Speaker 1' => 'bg-blue-400',
            'Speaker 2' => 'bg-violet-400',
            'Speaker 3' => 'bg-amber-400',
            'Speaker 4' => 'bg-rose-400',
            'Speaker 5' => 'bg-teal-400',
            'Speaker 6' => 'bg-indigo-400',
        ];
        $uniqueSpeakers = $transcription->segments->pluck('speaker')->filter()->unique()->values();
        $speakerTimelineColorMap = [];
        foreach ($uniqueSpeakers as $idx => $spk) {
            $speakerTimelineColorMap[$spk] = array_values($timelineColors)[$idx % count($timelineColors)];
        }
        $duration = $transcription->duration_seconds;
    @endphp

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6" id="speaker-timeline">
        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Speaker Timeline</h2>

        {{-- Legend --}}
        <div class="flex flex-wrap gap-3 mb-3">
            @foreach($uniqueSpeakers as $spk)
                <div class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded-full {{ $speakerTimelineColorMap[$spk] }}"></span>
                    <span class="text-xs text-gray-600 dark:text-gray-400">{{ $spk }}</span>
                </div>
            @endforeach
        </div>

        {{-- Timeline bar --}}
        <div class="relative w-full h-6 bg-gray-100 dark:bg-slate-700 rounded-full overflow-hidden flex">
            @foreach($transcription->segments->whereNotNull('speaker') as $seg)
                @php
                    $left  = round(($seg->start_time / $duration) * 100, 2);
                    $width = round((($seg->end_time - $seg->start_time) / $duration) * 100, 2);
                    $color = $speakerTimelineColorMap[$seg->speaker] ?? 'bg-gray-400';
                    $startFmt = sprintf('%02d:%02d', intdiv((int)$seg->start_time, 60), (int)$seg->start_time % 60);
                    $endFmt   = sprintf('%02d:%02d', intdiv((int)$seg->end_time, 60), (int)$seg->end_time % 60);
                @endphp
                <div
                    class="absolute h-full {{ $color }} opacity-80 hover:opacity-100 transition-opacity cursor-pointer group"
                    style="left: {{ $left }}%; width: {{ max($width, 0.5) }}%"
                    title="{{ $seg->speaker }}: {{ $startFmt }} – {{ $endFmt }}"
                >
                    {{-- Tooltip --}}
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block z-10">
                        <div class="bg-gray-900 text-white text-xs rounded px-2 py-1 whitespace-nowrap shadow">
                            {{ $seg->speaker }}: {{ $startFmt }}–{{ $endFmt }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
```

**Step 4: Add inline rename to speaker badges in the segments section**

Find the existing speaker badge in the segments loop:

```blade
@if($segment->speaker !== null)
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $speakerColorMap[$segment->speaker] }}">
        {{ $segment->speaker }}
    </span>
@endif
```

Replace with an Alpine.js inline-edit component:

```blade
@if($segment->speaker !== null)
    <span
        x-data="{
            editing: false,
            value: '{{ $segment->speaker }}',
            original: '{{ $segment->speaker }}',
            save() {
                if (this.value.trim() === '' || this.value === this.original) {
                    this.editing = false;
                    this.value = this.original;
                    return;
                }
                fetch('{{ route('meetings.transcriptions.speakers.update', [$meeting, $transcription]) }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ old_speaker: this.original, new_speaker: this.value.trim() }),
                }).then(r => {
                    if (r.ok) {
                        document.querySelectorAll('[data-speaker=\'' + this.original + '\']').forEach(el => {
                            el._x_dataStack[0].original = this.value.trim();
                            el._x_dataStack[0].value = this.value.trim();
                        });
                        this.original = this.value.trim();
                    }
                    this.editing = false;
                });
            }
        }"
        data-speaker="{{ $segment->speaker }}"
    >
        <template x-if="!editing">
            <button
                @click="editing = true"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium {{ $speakerColorMap[$segment->speaker] }} hover:ring-2 hover:ring-offset-1 hover:ring-blue-400 transition-all cursor-pointer"
                title="Click to rename speaker"
            >
                <span x-text="value"></span>
                <svg class="w-3 h-3 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
            </button>
        </template>
        <template x-if="editing">
            <input
                x-model="value"
                @keydown.enter="save()"
                @keydown.escape="editing = false; value = original"
                @blur="save()"
                x-init="$nextTick(() => $el.focus())"
                class="inline-flex px-2 py-0.5 rounded text-xs font-medium border border-blue-400 outline-none w-28"
                maxlength="100"
            />
        </template>
    </span>
@endif
```

**Step 5: Run test to verify it passes**

```bash
php artisan test --compact --filter="shows speaker timeline"
```
Expected: PASS.

**Step 6: Commit**

```bash
git add resources/views/transcriptions/show.blade.php
git commit -m "feat: speaker timeline visualization and inline rename UI (B4)"
```

---

## Task 7: AiSearchService (E2)

**Files:**
- Create: `app/Domain/Search/Services/AiSearchService.php`
- Test: `tests/Feature/Domain/Search/AiSearchServiceTest.php`

**Step 1: Create the service**

```bash
php artisan make:class app/Domain/Search/Services/AiSearchService --no-interaction
```

**Step 2: Write failing test**

Create `tests/Feature/Domain/Search/AiSearchServiceTest.php`:

```php
<?php

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Search\Services\AiSearchService;
use App\Models\Organization;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('returns cached response when available', function (): void {
    $org = Organization::factory()->create();
    $cacheKey = 'ai_search:'.$org->id.':'.md5('test query');

    Cache::put($cacheKey, [
        'answer' => 'Cached answer',
        'sources' => [],
    ], 3600);

    $service = app(AiSearchService::class);
    $result = $service->search('test query', $org->id);

    expect($result['answer'])->toBe('Cached answer');
});

it('assembles context from matching meetings', function (): void {
    $org = Organization::factory()->create();
    $meeting = MinutesOfMeeting::factory()->for($org)->create([
        'title' => 'Budget Review',
        'summary' => 'Discussed budget allocation',
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'AI answer here']]],
        ]),
    ]);

    $service = app(AiSearchService::class);
    $result = $service->search('budget', $org->id);

    expect($result)->toHaveKeys(['answer', 'sources']);
    expect($result['sources'])->toBeArray();
});
```

**Step 3: Run tests to verify they fail**

```bash
php artisan test --compact --filter=AiSearchServiceTest
```
Expected: FAIL.

**Step 4: Implement AiSearchService**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Search\Services;

use App\Domain\Account\Models\AiProviderConfig;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\AIProviderFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AiSearchService
{
    private const MAX_TOKENS_PER_MEETING = 1000;

    private const MAX_TOTAL_TOKENS = 8000;

    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly GlobalSearchService $searchService,
        private readonly AIProviderFactory $aiProviderFactory,
    ) {}

    /**
     * @return array{answer: string, sources: array<int, array<string, mixed>>}
     */
    public function search(string $query, int $organizationId): array
    {
        $cacheKey = 'ai_search:'.$organizationId.':'.md5($query);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($query, $organizationId) {
            return $this->performSearch($query, $organizationId);
        });
    }

    /**
     * @return array{answer: string, sources: array<int, array<string, mixed>>}
     */
    private function performSearch(string $query, int $organizationId): array
    {
        $searchResults = $this->searchService->search($query, $organizationId);
        $meetingIds = array_column($searchResults['meetings'], 'id');

        if (empty($meetingIds)) {
            return [
                'answer' => 'No relevant meetings found for your query.',
                'sources' => [],
            ];
        }

        $meetings = MinutesOfMeeting::query()
            ->whereIn('id', $meetingIds)
            ->with(['transcriptions:id,minutes_of_meeting_id,full_text', 'actionItems:id,minutes_of_meeting_id,title,status'])
            ->get();

        [$context, $sources] = $this->assembleContext($meetings, $query);

        $answer = $this->queryAi($query, $context, $organizationId);

        return ['answer' => $answer, 'sources' => $sources];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, MinutesOfMeeting>  $meetings
     * @return array{0: string, 1: array<int, array<string, mixed>>}
     */
    private function assembleContext(\Illuminate\Database\Eloquent\Collection $meetings, string $query): array
    {
        $contextParts = [];
        $sources = [];
        $totalTokens = 0;

        foreach ($meetings as $meeting) {
            if ($totalTokens >= self::MAX_TOTAL_TOKENS) {
                break;
            }

            $meetingContext = $this->buildMeetingContext($meeting);
            $estimatedTokens = (int) (strlen($meetingContext) / 4);

            if ($estimatedTokens > self::MAX_TOKENS_PER_MEETING) {
                $meetingContext = Str::limit($meetingContext, self::MAX_TOKENS_PER_MEETING * 4);
                $estimatedTokens = self::MAX_TOKENS_PER_MEETING;
            }

            $totalTokens += $estimatedTokens;
            $contextParts[] = $meetingContext;
            $sources[] = [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'meeting_date' => $meeting->meeting_date?->toDateString(),
                'url' => route('meetings.show', $meeting),
            ];
        }

        return [implode("\n\n---\n\n", $contextParts), $sources];
    }

    private function buildMeetingContext(MinutesOfMeeting $meeting): string
    {
        $parts = [
            'Meeting: '.$meeting->title,
            'Date: '.($meeting->meeting_date?->toDateString() ?? 'Unknown'),
        ];

        if ($meeting->summary) {
            $parts[] = 'Summary: '.$meeting->summary;
        }

        $transcriptionText = $meeting->transcriptions->first()?->full_text;
        if ($transcriptionText) {
            $parts[] = 'Transcript: '.Str::limit($transcriptionText, 600);
        }

        if ($meeting->actionItems->isNotEmpty()) {
            $items = $meeting->actionItems->map(fn ($a) => '- ['.$a->status->value.'] '.$a->title)->join("\n");
            $parts[] = "Action Items:\n".$items;
        }

        return implode("\n", $parts);
    }

    private function queryAi(string $query, string $context, int $organizationId): string
    {
        $providerConfig = AiProviderConfig::query()
            ->where('organization_id', $organizationId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();

        if (! $providerConfig) {
            $providerConfig = AiProviderConfig::query()
                ->where('is_active', true)
                ->first();
        }

        if (! $providerConfig) {
            return 'AI provider not configured. Please set up an AI provider in your organization settings.';
        }

        $provider = $this->aiProviderFactory->make($providerConfig->provider, [
            'api_key' => $providerConfig->decryptedApiKey(),
            'model' => $providerConfig->model,
            'base_url' => $providerConfig->base_url,
        ]);

        $systemPrompt = 'You are a meeting intelligence assistant. Answer the user\'s question based ONLY on the meeting records provided. Be concise and cite specific meeting details. If the answer cannot be found in the provided meetings, say so clearly.';

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => "Meeting records:\n\n{$context}\n\nQuestion: {$query}"],
        ];

        return $provider->chat($messages);
    }

    public function invalidateCache(int $organizationId): void
    {
        // Cache keys are per-query — use cache tags if supported, else accept TTL-based expiry.
        // For Redis: Cache::tags(['ai_search_'.$organizationId])->flush();
    }
}
```

**Step 5: Run tests to verify they pass**

```bash
php artisan test --compact --filter=AiSearchServiceTest
```
Expected: PASS.

**Step 6: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 7: Commit**

```bash
git add app/Domain/Search/Services/AiSearchService.php tests/Feature/Domain/Search/AiSearchServiceTest.php
git commit -m "feat: add AiSearchService with context assembly and caching (E2)"
```

---

## Task 8: AiSearchController with SSE Streaming (E2)

**Files:**
- Create: `app/Domain/Search/Controllers/AiSearchController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/Search/AiSearchControllerTest.php`

**Step 1: Create controller**

```bash
php artisan make:class app/Domain/Search/Controllers/AiSearchController --no-interaction
```

**Step 2: Write failing test**

Create `tests/Feature/Domain/Search/AiSearchControllerTest.php`:

```php
<?php

use App\Domain\Search\Services\AiSearchService;
use App\Models\Organization;
use App\Models\User;

it('returns ai search results as json', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->for($org, 'organization')->create();

    $this->mock(AiSearchService::class, function ($mock): void {
        $mock->shouldReceive('search')
            ->once()
            ->andReturn([
                'answer' => 'The budget was approved.',
                'sources' => [['id' => 1, 'title' => 'Budget Meeting', 'meeting_date' => '2026-01-01', 'url' => '/meetings/1']],
            ]);
    });

    $this->actingAs($user)
        ->postJson(route('search.ai'), ['query' => 'budget decision'])
        ->assertOk()
        ->assertJsonStructure(['answer', 'sources']);
});

it('validates that query is required', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->for($org, 'organization')->create();

    $this->actingAs($user)
        ->postJson(route('search.ai'), [])
        ->assertUnprocessable();
});

it('requires authentication', function (): void {
    $this->postJson(route('search.ai'), ['query' => 'test'])
        ->assertUnauthorized();
});
```

**Step 3: Run tests to verify they fail**

```bash
php artisan test --compact --filter=AiSearchControllerTest
```
Expected: FAIL — route does not exist.

**Step 4: Implement AiSearchController**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Search\Controllers;

use App\Domain\Search\Services\AiSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AiSearchController extends Controller
{
    public function __construct(
        private readonly AiSearchService $aiSearchService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $result = $this->aiSearchService->search(
            $validated['query'],
            $request->user()->current_organization_id,
        );

        return response()->json($result);
    }
}
```

**Step 5: Add route to `routes/web.php`**

After the existing `GET /search` route (around line 86), add:

```php
Route::post('search/ai', \App\Domain\Search\Controllers\AiSearchController::class)->name('search.ai');
```

**Step 6: Run tests to verify they pass**

```bash
php artisan test --compact --filter=AiSearchControllerTest
```
Expected: PASS.

**Step 7: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 8: Commit**

```bash
git add app/Domain/Search/Controllers/AiSearchController.php routes/web.php tests/Feature/Domain/Search/AiSearchControllerTest.php
git commit -m "feat: add AiSearchController with POST /search/ai route (E2)"
```

---

## Task 9: Search Page View with AI Tab (E2)

**Files:**
- Modify: `app/Domain/Search/Controllers/SearchController.php`
- Create: `resources/views/search/index.blade.php`
- Test: update `tests/Feature/Domain/Search/GlobalSearchTest.php`

**Step 1: Update SearchController to render view on GET**

Replace the `index` method in `SearchController`:

```php
public function index(Request $request): \Illuminate\Http\JsonResponse|\Illuminate\View\View
{
    if (! $request->has('q')) {
        return view('search.index');
    }

    $request->validate([
        'q' => 'required|string|min:2|max:100',
    ]);

    $results = $this->searchService->search(
        $request->string('q')->toString(),
        $request->user()->current_organization_id,
    );

    if ($request->wantsJson()) {
        return response()->json($results);
    }

    return view('search.index', compact('results'))->with('query', $request->string('q')->toString());
}
```

**Step 2: Create search view**

Create `resources/views/search/index.blade.php`:

```blade
@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6" x-data="{
    activeTab: 'search',
    aiQuery: '',
    aiLoading: false,
    aiAnswer: '',
    aiSources: [],
    aiError: '',

    async submitAiSearch() {
        if (this.aiQuery.trim().length < 3) return;
        this.aiLoading = true;
        this.aiAnswer = '';
        this.aiSources = [];
        this.aiError = '';

        try {
            const response = await fetch('{{ route('search.ai') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ query: this.aiQuery }),
            });

            const data = await response.json();

            if (response.ok) {
                this.aiAnswer = data.answer;
                this.aiSources = data.sources ?? [];
            } else {
                this.aiError = data.message ?? 'Something went wrong. Please try again.';
            }
        } catch (e) {
            this.aiError = 'Network error. Please try again.';
        } finally {
            this.aiLoading = false;
        }
    }
}">

    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Search</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Search across all your meetings, action items, and projects.</p>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 dark:border-slate-700">
        <nav class="-mb-px flex gap-6">
            <button
                @click="activeTab = 'search'"
                :class="activeTab === 'search'
                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                class="py-3 px-1 border-b-2 text-sm font-medium transition-colors"
            >
                Search
            </button>
            <button
                @click="activeTab = 'ai'"
                :class="activeTab === 'ai'
                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                    : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                class="py-3 px-1 border-b-2 text-sm font-medium transition-colors flex items-center gap-1.5"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
                </svg>
                AI Search
            </button>
        </nav>
    </div>

    {{-- Search Tab --}}
    <div x-show="activeTab === 'search'" x-cloak>
        <form method="GET" action="{{ route('search') }}" class="flex gap-3">
            <input
                type="text"
                name="q"
                value="{{ $query ?? '' }}"
                placeholder="Search meetings, action items, projects..."
                class="flex-1 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-900 dark:text-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                autofocus
            />
            <button type="submit" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                Search
            </button>
        </form>

        @if(isset($results))
            <div class="space-y-6 mt-6">
                {{-- Meetings --}}
                @if(!empty($results['meetings']))
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Meetings</h3>
                        <div class="space-y-2">
                            @foreach($results['meetings'] as $item)
                                <a href="{{ $item['url'] }}" class="flex items-center justify-between p-3 bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['title'] }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item['mom_number'] }} · {{ $item['meeting_date'] }}</div>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">{{ $item['status'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Action Items --}}
                @if(!empty($results['action_items']))
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Action Items</h3>
                        <div class="space-y-2">
                            @foreach($results['action_items'] as $item)
                                <a href="{{ $item['url'] ?? '#' }}" class="flex items-center justify-between p-3 bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['title'] }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $item['meeting_title'] }}</div>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400">{{ $item['priority'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Projects --}}
                @if(!empty($results['projects']))
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Projects</h3>
                        <div class="space-y-2">
                            @foreach($results['projects'] as $item)
                                <a href="{{ $item['url'] }}" class="block p-3 bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['title'] }}</div>
                                    @if($item['description'])
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $item['description'] }}</div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(empty($results['meetings']) && empty($results['action_items']) && empty($results['projects']))
                    <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">No results found for "{{ $query }}".</p>
                @endif
            </div>
        @endif
    </div>

    {{-- AI Search Tab --}}
    <div x-show="activeTab === 'ai'" x-cloak class="space-y-4">
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <p class="text-sm text-blue-700 dark:text-blue-300">
                Ask a question about any of your meetings. AI will search across all meeting records and provide an answer with sources.
            </p>
        </div>

        <div class="flex gap-3">
            <textarea
                x-model="aiQuery"
                @keydown.meta.enter="submitAiSearch()"
                @keydown.ctrl.enter="submitAiSearch()"
                placeholder="e.g. What decisions were made about the budget? Who was assigned to the marketing task?"
                rows="3"
                class="flex-1 rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-900 dark:text-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
            ></textarea>
            <button
                @click="submitAiSearch()"
                :disabled="aiLoading || aiQuery.trim().length < 3"
                class="self-end bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
                <template x-if="aiLoading">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </template>
                <span x-text="aiLoading ? 'Searching...' : 'Ask AI'"></span>
            </button>
        </div>
        <p class="text-xs text-gray-400 dark:text-gray-500">Press ⌘+Enter to submit</p>

        {{-- Error --}}
        <template x-if="aiError">
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <p class="text-sm text-red-700 dark:text-red-300" x-text="aiError"></p>
            </div>
        </template>

        {{-- Answer --}}
        <template x-if="aiAnswer">
            <div class="space-y-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                    <div class="flex items-center gap-2 mb-3">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-2"/>
                        </svg>
                        <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">AI Answer</span>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-wrap" x-text="aiAnswer"></p>
                </div>

                {{-- Sources --}}
                <template x-if="aiSources.length > 0">
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Sources</h3>
                        <div class="space-y-2">
                            <template x-for="source in aiSources" :key="source.id">
                                <a :href="source.url" class="flex items-center justify-between p-3 bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-600 transition-colors">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white" x-text="source.title"></span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500" x-text="source.meeting_date"></span>
                                </a>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

</div>
@endsection
```

**Step 3: Write failing test**

Add to `tests/Feature/Domain/Search/GlobalSearchTest.php`:

```php
it('renders the search page without query', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->for($org, 'organization')->create();

    $this->actingAs($user)
        ->get(route('search'))
        ->assertOk()
        ->assertViewIs('search.index');
});

it('renders search results when query is provided', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->for($org, 'organization')->create();
    MinutesOfMeeting::factory()->for($org)->create(['title' => 'Budget Review 2026']);

    $this->actingAs($user)
        ->get(route('search', ['q' => 'budget']))
        ->assertOk()
        ->assertViewHas('results')
        ->assertSee('Budget Review 2026');
});
```

**Step 4: Add `GET /search` route to web.php**

Find the existing search route (around line 86). It currently returns JSON. Replace or add alongside:

```php
Route::get('search', [\App\Domain\Search\Controllers\SearchController::class, 'index'])->name('search');
Route::post('search/ai', \App\Domain\Search\Controllers\AiSearchController::class)->name('search.ai');
```

**Step 5: Run all search tests**

```bash
php artisan test --compact --filter=Search
```
Expected: All pass.

**Step 6: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 7: Commit**

```bash
git add app/Domain/Search/Controllers/SearchController.php resources/views/search/index.blade.php routes/web.php
git commit -m "feat: search page with AI tab, keyword results, and source citations (E2)"
```

---

## Task 10: Final Integration Test & Pint

**Step 1: Run full test suite**

```bash
php artisan test --compact
```
Expected: All existing tests still pass, new tests pass.

**Step 2: Run Pint on all modified files**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 3: Commit any Pint fixes if needed**

```bash
git add -p
git commit -m "style: apply Pint formatting to Phase 1 MVP gap implementations"
```

---

## Summary of New Files

| File | Feature |
|------|---------|
| `resources/views/components/language-select.blade.php` | B5 |
| `app/Domain/Transcription/Controllers/SpeakerController.php` | B4 |
| `app/Domain/Search/Services/AiSearchService.php` | E2 |
| `app/Domain/Search/Controllers/AiSearchController.php` | E2 |
| `resources/views/search/index.blade.php` | E2 |

## Summary of Modified Files

| File | Change |
|------|--------|
| `resources/views/meetings/tabs/transcription.blade.php` | Language selector in upload form |
| `resources/js/audio-recorder.js` | Language state + dropdown |
| `app/Domain/Transcription/Jobs/ProcessTranscriptionJob.php` | Speaker auto-grouping |
| `resources/views/transcriptions/show.blade.php` | Speaker timeline + inline rename |
| `app/Domain/Search/Controllers/SearchController.php` | Return view on GET |
| `routes/web.php` | SpeakerController + AiSearchController routes |
