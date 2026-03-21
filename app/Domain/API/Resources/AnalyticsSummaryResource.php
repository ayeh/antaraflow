<?php

declare(strict_types=1);

namespace App\Domain\API\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_meetings' => $this->total_meetings,
            'total_action_items' => $this->total_action_items,
            'completed_action_items' => $this->completed_action_items,
            'overdue_action_items' => $this->overdue_action_items,
            'ai_usage_count' => $this->ai_usage_count,
            'snapshot_date' => $this->snapshot_date,
        ];
    }
}
