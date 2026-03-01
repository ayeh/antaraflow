<?php

declare(strict_types=1);

namespace App\Domain\Collaboration\Controllers;

use App\Domain\Collaboration\Models\MeetingShare;
use App\Domain\Collaboration\Requests\CreateShareRequest;
use App\Domain\Collaboration\Services\ShareService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\SharePermission;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ShareController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ShareService $shareService,
    ) {}

    public function index(MinutesOfMeeting $meeting): View
    {
        $this->authorize('viewAny', MeetingShare::class);

        $shares = $this->shareService->getSharesForMeeting($meeting);

        return view('collaboration.share-panel', compact('meeting', 'shares'));
    }

    public function store(CreateShareRequest $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->authorize('create', MeetingShare::class);

        $validated = $request->validated();
        $permission = SharePermission::from($validated['permission']);
        $expiresAt = isset($validated['expires_at']) ? \Carbon\Carbon::parse($validated['expires_at']) : null;

        if ($request->boolean('is_link_share') || empty($validated['shared_with_user_id'])) {
            $this->shareService->generateShareLink($meeting, $permission, $request->user(), $expiresAt);
        } else {
            $this->shareService->shareWithUser($meeting, (int) $validated['shared_with_user_id'], $permission, $request->user());
        }

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Meeting shared successfully.');
    }

    public function destroy(MinutesOfMeeting $meeting, MeetingShare $share): RedirectResponse
    {
        $this->authorize('delete', $share);

        $this->shareService->revokeShare($share);

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Share revoked.');
    }
}
