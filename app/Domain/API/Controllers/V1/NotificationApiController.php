<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers\V1;

use App\Domain\Account\Models\Organization;
use App\Domain\API\Controllers\ApiController;
use App\Domain\API\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $userIds = $this->orgMemberIds($orgId);

        $query = DatabaseNotification::query()
            ->whereIn('notifiable_id', $userIds)
            ->where('notifiable_type', User::class);

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->latest()->paginate(20);

        return response()->json([
            'data' => NotificationResource::collection($notifications->items()),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
                'unread_count' => DatabaseNotification::query()
                    ->whereIn('notifiable_id', $userIds)
                    ->where('notifiable_type', User::class)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function markRead(Request $request, string $notificationId): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $userIds = $this->orgMemberIds($orgId);

        $notification = DatabaseNotification::query()
            ->whereIn('notifiable_id', $userIds)
            ->where('notifiable_type', User::class)
            ->where('id', $notificationId)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json(new NotificationResource($notification));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $orgId = $this->organizationId($request);
        $userIds = $this->orgMemberIds($orgId);

        DatabaseNotification::query()
            ->whereIn('notifiable_id', $userIds)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function orgMemberIds(int $orgId): \Illuminate\Support\Collection
    {
        return Organization::findOrFail($orgId)
            ->members()
            ->pluck('users.id');
    }
}
