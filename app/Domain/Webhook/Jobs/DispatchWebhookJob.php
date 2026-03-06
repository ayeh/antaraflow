<?php

declare(strict_types=1);

namespace App\Domain\Webhook\Jobs;

use App\Domain\Webhook\Models\WebhookDelivery;
use App\Domain\Webhook\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 900, 3600];

    public function __construct(
        public WebhookEndpoint $endpoint,
        public string $event,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $body = [
            'event' => $this->event,
            'data' => $this->payload,
            'timestamp' => now()->toIso8601String(),
        ];

        $jsonBody = json_encode($body);
        $signature = hash_hmac('sha256', $jsonBody, $this->endpoint->secret);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Signature-256' => $signature,
                    'X-Webhook-Event' => $this->event,
                ])
                ->withBody($jsonBody, 'application/json')
                ->post($this->endpoint->url);

            $delivery = WebhookDelivery::query()->create([
                'webhook_endpoint_id' => $this->endpoint->id,
                'event' => $this->event,
                'payload' => $body,
                'response_status' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 5000),
                'attempt' => $this->attempts(),
                'successful' => $response->successful(),
            ]);

            if ($response->successful()) {
                $this->endpoint->resetFailures();
            } else {
                $this->endpoint->recordFailure();
                $this->release($this->backoff[$this->attempts() - 1] ?? 3600);
            }
        } catch (\Exception $e) {
            WebhookDelivery::query()->create([
                'webhook_endpoint_id' => $this->endpoint->id,
                'event' => $this->event,
                'payload' => $body,
                'response_status' => null,
                'response_body' => mb_substr($e->getMessage(), 0, 5000),
                'attempt' => $this->attempts(),
                'successful' => false,
            ]);

            $this->endpoint->recordFailure();

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 3600);
            }
        }
    }
}
