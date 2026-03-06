<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MeetingResolution;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Requests\CreateResolutionRequest;
use App\Domain\Meeting\Requests\UpdateResolutionRequest;
use App\Domain\Meeting\Services\ResolutionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class ResolutionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ResolutionService $resolutionService,
    ) {}

    public function store(CreateResolutionRequest $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->authorize('create', MeetingResolution::class);

        $this->resolutionService->create($meeting, $request->validated());

        return redirect()->route('meetings.show', ['meeting' => $meeting, 'step' => 5])
            ->with('success', 'Resolution created successfully.');
    }

    public function update(UpdateResolutionRequest $request, MinutesOfMeeting $meeting, MeetingResolution $resolution): RedirectResponse
    {
        $this->authorize('update', $resolution);

        $this->resolutionService->update($resolution, $request->validated());

        return redirect()->route('meetings.show', ['meeting' => $meeting, 'step' => 5])
            ->with('success', 'Resolution updated successfully.');
    }

    public function destroy(MinutesOfMeeting $meeting, MeetingResolution $resolution): RedirectResponse
    {
        $this->authorize('delete', $resolution);

        $this->resolutionService->delete($resolution);

        return redirect()->route('meetings.show', ['meeting' => $meeting, 'step' => 5])
            ->with('success', 'Resolution deleted successfully.');
    }
}
