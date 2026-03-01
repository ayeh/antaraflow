<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MeetingSeries;
use App\Domain\Meeting\Requests\CreateMeetingSeriesRequest;
use App\Domain\Meeting\Requests\UpdateMeetingSeriesRequest;
use App\Domain\Meeting\Services\MeetingSeriesService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class MeetingSeriesController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private MeetingSeriesService $meetingSeriesService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', MeetingSeries::class);

        $series = MeetingSeries::query()
            ->withCount('meetings')
            ->latest()
            ->get();

        return view('meeting-series.index', compact('series'));
    }

    public function create(): View
    {
        $this->authorize('create', MeetingSeries::class);

        return view('meeting-series.create');
    }

    public function store(CreateMeetingSeriesRequest $request): RedirectResponse
    {
        $this->authorize('create', MeetingSeries::class);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $series = $this->meetingSeriesService->create($data, $request->user());

        return redirect()->route('meeting-series.show', $series)
            ->with('success', 'Meeting series created successfully.');
    }

    public function show(MeetingSeries $meetingSeries): View
    {
        $this->authorize('view', $meetingSeries);

        $meetingSeries->load(['meetings' => function ($query) {
            $query->latest('meeting_date')->limit(20);
        }]);

        return view('meeting-series.show', compact('meetingSeries'));
    }

    public function edit(MeetingSeries $meetingSeries): View
    {
        $this->authorize('update', $meetingSeries);

        return view('meeting-series.edit', compact('meetingSeries'));
    }

    public function update(UpdateMeetingSeriesRequest $request, MeetingSeries $meetingSeries): RedirectResponse
    {
        $this->authorize('update', $meetingSeries);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', true);

        $this->meetingSeriesService->update($meetingSeries, $data);

        return redirect()->route('meeting-series.show', $meetingSeries)
            ->with('success', 'Meeting series updated successfully.');
    }

    public function destroy(MeetingSeries $meetingSeries): RedirectResponse
    {
        $this->authorize('delete', $meetingSeries);

        $this->meetingSeriesService->delete($meetingSeries);

        return redirect()->route('meeting-series.index')
            ->with('success', 'Meeting series deleted successfully.');
    }

    public function generateMeetings(Request $request, MeetingSeries $meetingSeries): RedirectResponse
    {
        $this->authorize('update', $meetingSeries);

        $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $this->meetingSeriesService->generateUpcoming(
            $meetingSeries,
            $request->integer('count'),
            $request->user(),
        );

        return redirect()->route('meeting-series.show', $meetingSeries)
            ->with('success', "Generated {$request->integer('count')} upcoming meetings.");
    }
}
