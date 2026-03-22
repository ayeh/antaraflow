<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Controllers;

use App\Domain\Analytics\Services\IntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class IntelligenceController extends Controller
{
    public function __construct(
        private readonly IntelligenceService $intelligenceService,
    ) {}

    public function index(): View
    {
        return view('analytics.intelligence');
    }

    public function data(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $org = auth()->user()->currentOrganization;
        $orgId = (int) $org->id;
        $startDate = $request->date('start_date') ?? now()->subMonths(6)->startOfMonth();
        $endDate = $request->date('end_date') ?? now()->endOfMonth();

        return response()->json($this->intelligenceService->getAllMetrics($orgId, $startDate, $endDate));
    }
}
