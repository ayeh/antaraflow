<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\API\Resources\ActionItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ActionItemApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->attributes->get('organization_id');

        $query = ActionItem::query()
            ->where('organization_id', $orgId);

        if ($request->filled('meeting_id')) {
            $query->where('minutes_of_meeting_id', $request->integer('meeting_id'));
        }

        if ($request->filled('status')) {
            $query->whereRaw('LOWER(status) = ?', [strtolower($request->string('status')->toString())]);
        }

        $actionItems = $query->latest()->paginate(20);

        return response()->json([
            'data' => ActionItemResource::collection($actionItems->items()),
            'meta' => [
                'current_page' => $actionItems->currentPage(),
                'last_page' => $actionItems->lastPage(),
                'total' => $actionItems->total(),
            ],
        ]);
    }
}
