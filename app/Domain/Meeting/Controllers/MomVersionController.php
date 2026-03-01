<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomVersion;
use App\Domain\Meeting\Services\VersionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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

        return view('meetings.versions.show', compact('meeting', 'version'));
    }
}
