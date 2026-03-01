<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MeetingApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->attributes->get('organization_id');

        $meetings = MinutesOfMeeting::query()
            ->where('organization_id', $orgId)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $meetings->items(),
            'meta' => [
                'current_page' => $meetings->currentPage(),
                'last_page' => $meetings->lastPage(),
                'total' => $meetings->total(),
            ],
        ]);
    }
}
