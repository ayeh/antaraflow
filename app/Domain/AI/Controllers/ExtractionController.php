<?php

declare(strict_types=1);

namespace App\Domain\AI\Controllers;

use App\Domain\AI\Jobs\ExtractMeetingDataJob;
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
