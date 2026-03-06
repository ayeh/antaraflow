<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\Channels;

use App\Infrastructure\Notifications\Messages\TeamsMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeamsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTeams')) {
            return;
        }

        $webhookUrl = $this->resolveWebhookUrl($notifiable);

        if (! $webhookUrl) {
            return;
        }

        /** @var TeamsMessage $message */
        $message = $notification->toTeams($notifiable);

        $response = Http::timeout(10)->post($webhookUrl, $message->toAdaptiveCard());

        if ($response->failed()) {
            Log::warning('Teams webhook delivery failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'notification' => $notification::class,
            ]);
        }
    }

    private function resolveWebhookUrl(object $notifiable): ?string
    {
        if (method_exists($notifiable, 'routeNotificationForTeams')) {
            return $notifiable->routeNotificationForTeams();
        }

        $organization = $notifiable->currentOrganization ?? null;

        return $organization?->teams_webhook_url;
    }
}
