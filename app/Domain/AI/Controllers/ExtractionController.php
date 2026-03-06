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

            return response()->json([
                'success' => false,
                'message' => 'Extraction failed: '.$e->getMessage(),
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
            return response()->json(['message' => 'AI extraction started.'], 202);
        }

        return back()->with('success', 'AI extraction started.');
    }

    public function index(MinutesOfMeeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $extractions = $meeting->extractions()->latest()->get();
        $topics = $meeting->topics()->orderBy('sort_order')->get();

        return view('extractions.index', compact('meeting', 'extractions', 'topics'));
    }
}
