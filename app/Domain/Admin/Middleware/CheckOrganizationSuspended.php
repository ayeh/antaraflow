<?php

declare(strict_types=1);

namespace App\Domain\Admin\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOrganizationSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->currentOrganization && $user->currentOrganization->is_suspended) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $user->currentOrganization->suspended_reason
                        ?: __('This organization is suspended.'),
                    'code' => 'ORGANIZATION_SUSPENDED',
                ], 403);
            }

            return response()->view('errors.suspended', [
                'reason' => $user->currentOrganization->suspended_reason,
            ], 403);
        }

        return $next($request);
    }
}
