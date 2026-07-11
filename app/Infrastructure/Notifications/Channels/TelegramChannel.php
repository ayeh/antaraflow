<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTelegram')) {
            return;
        }

        $token = config('services.telegram.bot_token');
        $chatId = $notifiable->routeNotificationFor('telegram', $notification);

        if (! $token || ! $chatId) {
            return;
        }

        $text = $notification->toTelegram($notifiable);

        $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);

        if ($response->failed()) {
            Log::warning('Telegram notification delivery failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'notification' => $notification::class,
            ]);
        }
    }
}
