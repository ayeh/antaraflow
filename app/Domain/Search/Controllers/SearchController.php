<?php

declare(strict_types=1);

namespace App\Domain\Search\Controllers;

use App\Domain\Search\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __construct(
        private readonly GlobalSearchService $searchService,
    ) {}

    public function index(Request $request): JsonResponse|View
    {
        if (! $request->has('q')) {
            return view('search.index');
        }

        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $results = $this->searchService->search(
            $request->string('q')->toString(),
            $request->user()->current_organization_id,
        );

        if ($request->wantsJson()) {
            return response()->json($results);
        }

        return view('search.index', [
            'results' => $results,
            'query' => $request->string('q')->toString(),
        ]);
    }
}
