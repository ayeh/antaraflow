<?php

declare(strict_types=1);

namespace App\Domain\AI\Controllers;

use App\Domain\AI\Events\ExtractionCompleted;
use App\Domain\AI\Events\ExtractionFailed;
use App\Domain\AI\Jobs\ExtractMeetingDataJob;
use App\Domain\AI\Services\ExtractionService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\View\View;

class ExtractionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ExtractionService $extractionService,
    ) {}

    /**
     * Run extraction synchronously and redirect to the Review step.
     */
    public function generate(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        set_time_limit(180);

        try {
            $this->extractionService->extractAll($meeting);
            $this->extractionService->createActionItemRecords($meeting, $request->user());

            event(new ExtractionCompleted($meeting));

            return response()->json([
                'success' => true,
                'message' => 'Meeting minutes generated successfully.',
                'redirect_url' => route('meetings.show', ['meeting' => $meeting, 'step' => 4]),
            ]);
        } catch (\Throwable $e) {
            event(new ExtractionFailed($meeting, $e->getMessage()));
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Extraction failed. Please try again or contact support.',
            ], 422);
        }
    }

    /**
     * Dispatch extraction as a background job (for API use).
     */
    public function extract(Request $request, MinutesOfMeeting $meeting): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $meeting);

        ExtractMeetingDataJob::dispatch($meeting);

        if ($request->wantsJson()) {
            return response()->json(['message' => __('AI extraction started.')], 202);
        }

        return back()->with('success', __('AI extraction started.'));
    }

    public function index(MinutesOfMeeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $extractions = $meeting->extractions()->latest()->get();
        $topics = $meeting->topics()->orderBy('sort_order')->get();

        return view('extractions.index', compact('meeting', 'extractions', 'topics'));
    }

    /**
     * Manually update extraction content (Summary, Decisions, Risks, Issues)
     * from the Finalize step inline editor.
     */
    public function update(Request $request, MinutesOfMeeting $meeting, string $type): RedirectResponse
    {
        $this->authorize('update', $meeting);

        abort_unless(in_array($type, ['summary', 'decisions', 'risks', 'issues'], true), 404);

        $validated = $request->validate($this->validationRulesFor($type));

        $extraction = $meeting->extractions()->firstOrNew(['type' => $type]);

        if ($type === 'summary') {
            $extraction->content = trim((string) ($validated['content'] ?? '')) ?: null;
        } else {
            $items = collect($validated['items'] ?? [])
                ->map(fn (array $item): array => $this->sanitizeItem($type, $item))
                ->filter(fn (array $item): bool => $this->itemHasContent($type, $item))
                ->values()
                ->all();

            $extraction->structured_data = $items;
            $extraction->content = $this->buildContentSummary($type, $items);
        }

        if (! $extraction->exists) {
            $extraction->provider = 'manual';
            $extraction->model = 'manual-edit';
        }

        $extraction->save();

        return back()->with('success', __(':type updated.', ['type' => ucfirst($type)]));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function validationRulesFor(string $type): array
    {
        return match ($type) {
            'summary' => [
                'content' => ['nullable', 'string', 'max:20000'],
            ],
            'decisions' => [
                'items' => ['nullable', 'array'],
                'items.*.decision' => ['nullable', 'string', 'max:2000'],
                'items.*.made_by' => ['nullable', 'string', 'max:255'],
            ],
            'risks' => [
                'items' => ['nullable', 'array'],
                'items.*.risk' => ['nullable', 'string', 'max:2000'],
                'items.*.severity' => ['nullable', 'in:high,medium,low'],
                'items.*.mitigation' => ['nullable', 'string', 'max:2000'],
                'items.*.raised_by' => ['nullable', 'string', 'max:255'],
            ],
            'issues' => [
                'items' => ['nullable', 'array'],
                'items.*.issue' => ['nullable', 'string', 'max:2000'],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function sanitizeItem(string $type, array $item): array
    {
        return match ($type) {
            'decisions' => [
                'decision' => trim((string) Arr::get($item, 'decision', '')),
                'made_by' => trim((string) Arr::get($item, 'made_by', '')) ?: null,
            ],
            'risks' => [
                'risk' => trim((string) Arr::get($item, 'risk', '')),
                'severity' => Arr::get($item, 'severity', 'medium'),
                'mitigation' => trim((string) Arr::get($item, 'mitigation', '')) ?: null,
                'raised_by' => trim((string) Arr::get($item, 'raised_by', '')) ?: null,
            ],
            'issues' => [
                'issue' => trim((string) Arr::get($item, 'issue', '')),
            ],
            default => $item,
        };
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function itemHasContent(string $type, array $item): bool
    {
        return match ($type) {
            'decisions' => ! empty($item['decision']),
            'risks' => ! empty($item['risk']),
            'issues' => ! empty($item['issue']),
            default => false,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function buildContentSummary(string $type, array $items): ?string
    {
        if ($items === []) {
            return null;
        }

        return collect($items)
            ->map(fn (array $item): string => match ($type) {
                'decisions' => '- '.$item['decision'],
                'risks' => '- ['.$item['severity'].'] '.$item['risk'],
                'issues' => '- '.$item['issue'],
                default => '',
            })
            ->implode("\n");
    }
}
