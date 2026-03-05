<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Controllers;

use App\Domain\Calendar\Services\CalendarSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CalendarWebhookController extends Controller
{
    public function __construct(
        private readonly CalendarSyncService $calendarSyncService,
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        $calendarProvider = $this->calendarSyncService->resolveProvider($provider);
        $calendarProvider->handleWebhook($request);

        return response()->json(['status' => 'ok']);
    }
}
