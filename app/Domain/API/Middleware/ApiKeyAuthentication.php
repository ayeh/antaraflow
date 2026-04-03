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

        // Enforce IP allowlist if configured on the key
        $allowedIps = $apiKey->allowed_ips ?? [];
        if (! empty($allowedIps) && ! in_array($request->ip(), $allowedIps, true)) {
            return response()->json(['error' => 'Request IP is not allowed for this API key.'], 403);
        }

        // Debounce last_used_at update (only update if stale by 5+ minutes)
        if (! $apiKey->last_used_at || $apiKey->last_used_at->diffInMinutes(now()) >= 5) {
            $apiKey->update(['last_used_at' => now()]);
        }

        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('organization_id', $apiKey->organization_id);

        // Enforce API key permissions (default to read-only if not configured)
        $permissions = $apiKey->permissions ?? ['read'];
        if (! empty($permissions)) {
            $method = $request->method();
            $requiredPermission = match (true) {
                $method === 'GET' => 'read',
                in_array($method, ['POST', 'PUT', 'PATCH'], true) => 'write',
                $method === 'DELETE' => 'delete',
                default => 'read',
            };

            if (! in_array($requiredPermission, $permissions, true) && ! in_array('*', $permissions, true)) {
                return response()->json(['error' => 'API key does not have permission for this action.'], 403);
            }
        }

        return $next($request);
    }
}
