<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use Illuminate\Http\JsonResponse;

class ApiInfoController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name' => 'AntaraFlow API',
            'version' => 'v1',
            'description' => 'REST API for AntaraFlow — Minutes of Meeting platform',
            'endpoints' => [
                'meetings' => '/api/v1/meetings',
                'attendees' => '/api/v1/meetings/{id}/attendees',
                'transcriptions' => '/api/v1/meetings/{id}/transcriptions',
                'comments' => '/api/v1/meetings/{id}/comments',
                'analytics' => '/api/v1/analytics/summary',
                'webhooks' => '/api/v1/webhooks',
            ],
        ]);
    }
}
