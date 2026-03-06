<?php

declare(strict_types=1);

namespace App\Domain\AI\Controllers;

use App\Domain\AI\Services\MeetingPreparationService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Support\Enums\MeetingStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MeetingPreparationController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private MeetingPreparationService $preparationService,
    ) {}

    public function generate(MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        if (! in_array($meeting->status, [MeetingStatus::Draft, MeetingStatus::InProgress], true)) {
            return response()->json([
                'error' => 'Meeting preparation is only available for draft or in-progress meetings.',
            ], 422);
        }

        $data = $this->preparationService->generate($meeting);

        return response()->json($data);
    }

    public function apply(Request $request, MinutesOfMeeting $meeting): JsonResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'agenda' => ['required', 'array', 'min:1'],
            'agenda.*' => ['required', 'string', 'max:500'],
        ]);

        $metadata = $meeting->metadata ?? [];
        $metadata['agenda'] = $validated['agenda'];
        $meeting->update(['metadata' => $metadata]);

        return response()->json([
            'message' => 'Agenda applied successfully.',
        ]);
    }
}
