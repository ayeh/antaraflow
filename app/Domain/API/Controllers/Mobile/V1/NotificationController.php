<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\Mobile\V1;

use App\Domain\API\Controllers\Mobile\MobileController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends MobileController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $limit = min(max($request->integer('limit', 30), 1), 100);

        $notifications = $user->notifications()
            ->when($request->boolean('unread_only'), fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->cursorPaginate($limit, ['*'], 'cursor', $request->string('cursor')->toString() ?: null);

        return response()->json([
            'data' => $notifications->getCollection()->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'type' => $this->shortType($notification->type),
                'title' => $notification->data['title'] ?? null,
                'body' => $notification->data['message'] ?? $notification->data['body'] ?? null,
                'deep_link' => $notification->data['deep_link'] ?? null,
                'meeting_id' => $notification->data['meeting_id'] ?? null,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'next_cursor' => $notifications->nextCursor()?->encode(),
                'has_more' => $notifications->hasMorePages(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->user($request)->unreadNotifications()->count(),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $this->user($request)->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['read_at' => $notification->fresh()->read_at?->toIso8601String()]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(null, 204);
    }

    /**
     * The stored type is a fully-qualified class name. The app should not have
     * to know PHP namespaces, so it is reduced to a stable dotted key.
     */
    private function shortType(string $type): string
    {
        $base = class_basename($type);
        $base = preg_replace('/Notification$/', '', $base) ?? $base;

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '.$0', $base));
    }
}
