<?php

declare(strict_types=1);

namespace App\Domain\API\Middleware\Mobile;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replays the original response for a repeated `Idempotency-Key`.
 *
 * Mobile clients drain a write queue over an unreliable network, so the same
 * create will be retried whenever a response is lost in flight. Without this
 * every retry produces a duplicate action item or comment.
 *
 * A key is claimed before the request runs, so two retries racing each other
 * cannot both reach the controller. The claim is released when the handler
 * fails, letting the client retry a genuine error.
 */
class MobileIdempotency
{
    private const TTL_HOURS = 24;

    private const LOCK_SECONDS = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if ($key === null || $key === '' || ! $request->isMethod('POST')) {
            return $next($request);
        }

        $cacheKey = $this->cacheKey($request, $key);

        $stored = Cache::get($cacheKey);

        if (is_array($stored) && array_key_exists('body', $stored)) {
            return response($stored['body'], $stored['status'])
                ->withHeaders(['Content-Type' => 'application/json'])
                ->header('Idempotency-Replayed', 'true');
        }

        if (! Cache::add($cacheKey, ['pending' => true], now()->addSeconds(self::LOCK_SECONDS))) {
            return response()->json([
                'message' => __('A matching request is still being processed. Please try again shortly.'),
                'code' => 'IDEMPOTENCY_IN_PROGRESS',
            ], 409);
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            Cache::put($cacheKey, [
                'status' => $response->getStatusCode(),
                'body' => $response->getContent(),
            ], now()->addHours(self::TTL_HOURS));
        } else {
            Cache::forget($cacheKey);
        }

        return $response;
    }

    private function cacheKey(Request $request, string $key): string
    {
        return sprintf(
            'mobile:idempotency:%d:%s:%s',
            $request->user()?->id ?? 0,
            sha1($request->path()),
            sha1($key),
        );
    }
}
