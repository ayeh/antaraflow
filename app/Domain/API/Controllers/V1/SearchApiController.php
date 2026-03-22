<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\API\Controllers\ApiController;
use App\Domain\API\Resources\ActionItemResource;
use App\Domain\API\Resources\MeetingResource;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchApiController extends ApiController
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'type' => ['nullable', 'string', 'in:meetings,action_items,all'],
        ]);

        $orgId = $this->organizationId($request);
        $query = $request->string('q')->toString();
        $type = $request->string('type', 'all')->toString();

        $results = [];

        if (in_array($type, ['meetings', 'all'])) {
            $meetings = MinutesOfMeeting::query()
                ->where('organization_id', $orgId)
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                        ->orWhere('content', 'like', "%{$query}%")
                        ->orWhere('summary', 'like', "%{$query}%")
                        ->orWhere('location', 'like', "%{$query}%");
                })
                ->latest()
                ->limit(10)
                ->get();

            $results['meetings'] = MeetingResource::collection($meetings);
        }

        if (in_array($type, ['action_items', 'all'])) {
            $actionItems = ActionItem::query()
                ->where('organization_id', $orgId)
                ->where(function ($q) use ($query) {
                    $q->where('title', 'like', "%{$query}%")
                        ->orWhere('description', 'like', "%{$query}%");
                })
                ->latest()
                ->limit(10)
                ->get();

            $results['action_items'] = ActionItemResource::collection($actionItems);
        }

        return response()->json([
            'data' => $results,
            'query' => $query,
        ]);
    }
}
