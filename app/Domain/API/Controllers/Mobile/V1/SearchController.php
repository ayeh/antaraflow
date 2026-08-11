<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use App\Domain\Search\Services\AiSearchService;
use App\Domain\Search\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends MobileController
{
    public function __construct(
        private readonly GlobalSearchService $searchService,
        private readonly AiSearchService $aiSearchService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $results = $this->searchService->search(
            $validated['q'],
            $this->organizationId($request),
            $validated['limit'] ?? 20,
        );

        return response()->json([
            'data' => [
                'meetings' => $this->withDeepLinks($results['meetings'] ?? [], 'meetings'),
                'action_items' => $this->withDeepLinks($results['action_items'] ?? [], 'action-items'),
                'projects' => $results['projects'] ?? [],
            ],
        ]);
    }

    public function ai(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $result = $this->aiSearchService->search($validated['query'], $this->organizationId($request));

        return response()->json([
            'answer' => $result['answer'] ?? '',
            'citations' => $this->withDeepLinks($result['sources'] ?? [], 'meetings'),
        ]);
    }

    /**
     * Web URLs are not useful on a phone; swap them for a scheme the app can
     * route on while keeping the original for the "open in browser" fallback.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function withDeepLinks(array $rows, string $segment): array
    {
        return array_map(function (array $row) use ($segment): array {
            $row['deep_link'] = isset($row['id']) ? "antaraflow://{$segment}/{$row['id']}" : null;
            $row['web_url'] = $row['url'] ?? null;
            unset($row['url']);

            return $row;
        }, $rows);
    }
}
