<?php

declare(strict_types=1);

namespace App\Domain\Meeting\Controllers;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Requests\CirculateRequest;
use App\Domain\Meeting\Services\CirculationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class CirculateController extends Controller
{
    public function __construct(
        private readonly CirculationService $circulationService,
    ) {}

    public function __invoke(CirculateRequest $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        $this->circulationService->circulate(
            meeting: $meeting,
            sentBy: $request->user(),
            recipients: $request->validated('recipients'),
            subject: $request->validated('subject'),
            deadline: Carbon::parse($request->validated('deadline'))->endOfDay(),
            bodyNote: $request->validated('body_note'),
        );

        return redirect()->route('meetings.show', $meeting)
            ->with('success', 'Minit telah diedarkan untuk pengesahan.');
    }
}
