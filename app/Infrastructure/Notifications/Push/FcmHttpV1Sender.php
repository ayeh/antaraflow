<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications\Push;

use App\Domain\Account\Models\UserDevice;
use App\Support\Enums\DevicePlatform;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Delivers push through Firebase Cloud Messaging HTTP v1, for Android directly
 * and for iOS through the APNs bridge Firebase maintains.
 *
 * The OAuth access token is minted here from the service-account key rather
 * than through the Google client library, so pushing does not pull a large
 * dependency tree into the application.
 */
class FcmHttpV1Sender implements PushSender
{
    private const TOKEN_CACHE_KEY = 'fcm:access_token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function send(UserDevice $device, PushMessage $message): bool
    {
        if ($device->push_token === null) {
            return false;
        }

        $projectId = (string) config('services.fcm.project_id');
        $accessToken = $this->accessToken();

        if ($projectId === '' || $accessToken === null) {
            Log::warning('Push not sent: FCM is not configured.', ['device_id' => $device->device_id]);

            return true;
        }

        $response = Http::timeout(10)
            ->withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => $this->payload($device, $message),
            ]);

        if ($response->successful()) {
            return true;
        }

        // 404 UNREGISTERED / 400 INVALID_ARGUMENT on the token mean the app was
        // uninstalled or the token rotated. Reporting failure lets the caller
        // drop it rather than retrying on every future notification.
        if (in_array($response->status(), [400, 403, 404], true)) {
            Log::info('Push token rejected by FCM; dropping it.', [
                'device_id' => $device->device_id,
                'status' => $response->status(),
            ]);

            return false;
        }

        Log::warning('Push delivery failed.', [
            'device_id' => $device->device_id,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return true;
    }

    /** @return array<string, mixed> */
    private function payload(UserDevice $device, PushMessage $message): array
    {
        $payload = [
            'token' => $device->push_token,
            'notification' => [
                'title' => $message->title,
                'body' => $message->body,
            ],
            'data' => $message->dataPayload(),
        ];

        if ($device->platform === DevicePlatform::Ios) {
            $payload['apns'] = [
                'payload' => [
                    'aps' => array_filter([
                        'sound' => 'default',
                        'badge' => $message->badge,
                        'content-available' => 1,
                    ], fn ($value) => $value !== null),
                ],
            ];
        } else {
            $payload['android'] = [
                'priority' => 'high',
                'notification' => ['default_sound' => true],
            ];
        }

        return $payload;
    }

    private function accessToken(): ?string
    {
        $credentials = $this->credentials();

        if ($credentials === null) {
            return null;
        }

        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addMinutes(50), function () use ($credentials): ?string {
            $jwt = $this->signedAssertion($credentials);

            if ($jwt === null) {
                return null;
            }

            $response = Http::asForm()->post($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->failed()) {
                Log::error('Could not obtain an FCM access token.', ['status' => $response->status()]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    /** @param  array<string, mixed>  $credentials */
    private function signedAssertion(array $credentials): ?string
    {
        $now = time();

        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64Url(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now,
        ]));

        $signature = '';
        $signed = openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);

        if (! $signed) {
            Log::error('Could not sign the FCM service-account assertion.');

            return null;
        }

        return "{$header}.{$claims}.".$this->base64Url($signature);
    }

    /** @return array<string, mixed>|null */
    private function credentials(): ?array
    {
        $path = (string) config('services.fcm.credentials');

        if ($path === '' || ! is_readable($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) && isset($decoded['client_email'], $decoded['private_key'])
            ? $decoded
            : null;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
