<?php

declare(strict_types=1);

namespace App\Domain\AI\Controllers;

use App\Domain\AI\Jobs\ExtractMeetingDataJob;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ExtractionController extends Controller
{
    use AuthorizesRequests;

    public function extract(MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        ExtractMeetingDataJob::dispatch($meeting);

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
