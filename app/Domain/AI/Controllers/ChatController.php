<?php

declare(strict_types=1);

namespace App\Domain\AI\Controllers;

use App\Domain\AI\Services\ChatService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class ChatController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ChatService $chatService,
    ) {}

    public function index(MinutesOfMeeting $meeting, Request $request): View
    {
        $this->authorize('view', $meeting);

        $history = $this->chatService->getHistory($meeting, $request->user());

        return view('chat.index', compact('meeting', 'history'));
    }

    public function store(MinutesOfMeeting $meeting, Request $request): JsonResponse
    {
        $this->authorize('view', $meeting);

        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $response = $this->chatService->sendMessage(
            $meeting,
            $request->user(),
            $request->input('message'),
        );

        return response()->json([
            'message' => $response->message,
            'role' => $response->role,
            'created_at' => $response->created_at->toIso8601String(),
        ], 201);
    }
}
