<?php

declare(strict_types=1);

namespace App\Domain\Search\Controllers;

use App\Domain\Search\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SearchController extends Controller
{
    public function __construct(
        private readonly GlobalSearchService $searchService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $results = $this->searchService->search(
            $request->string('q')->toString(),
            $request->user()->current_organization_id,
        );

        return response()->json($results);
    }
}
