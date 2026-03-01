<?php

declare(strict_types=1);

namespace App\Domain\API\Middleware;

use App\Domain\Account\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuthentication
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (! $bearerToken) {
            return response()->json(['error' => 'API key required.'], 401);
        }

        $hash = hash('sha256', $bearerToken);

        $apiKey = ApiKey::query()
            ->where('secret_hash', $hash)
            ->where('is_active', true)
            ->first();

        if (! $apiKey) {
            return response()->json(['error' => 'Invalid or inactive API key.'], 401);
        }

        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            return response()->json(['error' => 'API key has expired.'], 401);
        }

        $apiKey->update(['last_used_at' => now()]);

        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('organization_id', $apiKey->organization_id);

        return $next($request);
    }
}
