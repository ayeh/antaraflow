<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Services\NotificationService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $notifications = $this->notificationService->getAll($user);

        return view('notifications.index', compact('notifications'));
    }

    public function unread(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $notifications = $this->notificationService->getUnread($user);
        $count = $this->notificationService->getUnreadCount($user);

        return response()->json(['notifications' => $notifications, 'count' => $count]);
    }

    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->notificationService->markAsRead($user, $id);

        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->notificationService->markAllAsRead($user);

        return back()->with('success', 'All notifications marked as read.');
    }
}
