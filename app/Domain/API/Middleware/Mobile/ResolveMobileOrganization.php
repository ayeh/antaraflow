<?php

declare(strict_types=1);

namespace App\Domain\API\Middleware\Mobile;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes the tenant context for mobile requests.
 *
 * The global OrganizationScope reads `current_organization_id` off the
 * authenticated user, so the mobile app switches tenant by sending an
 * `X-Organization-Id` header rather than by mutating stored state. The
 * override is applied in memory only: persisting it would silently move the
 * user's web session to a different organization from their phone.
 */
class ResolveMobileOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $requested = $request->header('X-Organization-Id');

        if ($requested !== null && $requested !== '') {
            $organizationId = (int) $requested;

            if (! $user->organizations()->whereKey($organizationId)->exists()) {
                return response()->json([
                    'message' => __('You do not have access to this organization.'),
                    'code' => 'ORGANIZATION_FORBIDDEN',
                ], 403);
            }

            $user->current_organization_id = $organizationId;

            // Policies read $user->currentOrganization; drop any copy loaded
            // against the previous id so it resolves against the new one.
            $user->unsetRelation('currentOrganization');
        }

        if (! $user->current_organization_id) {
            $firstOrganization = $user->organizations()->first();

            if ($firstOrganization === null) {
                return response()->json([
                    'message' => __('Your account is not linked to an organization yet.'),
                    'code' => 'NO_ORGANIZATION_CONTEXT',
                ], 409);
            }

            $user->current_organization_id = $firstOrganization->id;
        }

        return $next($request);
    }
}
