<?php

declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NotificationService
{
    public function getUnread(User $user): Collection
    {
        return $user->unreadNotifications()->latest()->limit(20)->get();
    }

    public function getAll(User $user): LengthAwarePaginator
    {
        return $user->notifications()->latest()->paginate(30);
    }

    public function markAsRead(User $user, string $notificationId): void
    {
        $user->notifications()->where('id', $notificationId)->first()?->markAsRead();
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function getUnreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }
}
