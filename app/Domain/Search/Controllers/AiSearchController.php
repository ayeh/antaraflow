<?php

declare(strict_types=1);

namespace App\Domain\Search\Controllers;

use App\Domain\Search\Services\AiSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AiSearchController extends Controller
{
    public function __construct(
        private readonly AiSearchService $aiSearchService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $result = $this->aiSearchService->search(
            $validated['query'],
            $request->user()->current_organization_id,
        );

        return response()->json($result);
    }
}
