<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomVersion;
use App\Domain\Meeting\Services\VersionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class MomVersionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private VersionService $versionService) {}

    public function index(MinutesOfMeeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $versions = $this->versionService->getVersionHistory($meeting);

        return view('meetings.versions.index', compact('meeting', 'versions'));
    }

    public function show(MinutesOfMeeting $meeting, MomVersion $version): View
    {
        $this->authorize('view', $meeting);

        abort_if($version->minutes_of_meeting_id !== $meeting->id, 404);

        $latestVersionNumber = $meeting->versions()->max('version_number');
        $isLatest = $version->version_number === $latestVersionNumber;

        return view('meetings.versions.show', compact('meeting', 'version', 'isLatest'));
    }

    public function restore(Request $request, MinutesOfMeeting $meeting, MomVersion $version): RedirectResponse
    {
        $this->authorize('restoreVersion', $meeting);

        abort_if($version->minutes_of_meeting_id !== $meeting->id, 404);

        $this->versionService->restoreVersion($meeting, $version, $request->user());

        return redirect()->route('meetings.show', $meeting)
            ->with('success', __('Restored version :number.', ['number' => $version->version_number]));
    }
}
