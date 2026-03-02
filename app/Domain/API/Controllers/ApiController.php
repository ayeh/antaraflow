<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers;

use App\Domain\Account\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Base controller for all API controllers.
 *
 * IMPORTANT: The global OrganizationScope is inactive in this API layer
 * because the middleware sets organization_id via request attributes rather
 * than through an authenticated user. Every query MUST be explicitly scoped
 * using ->where('organization_id', $this->organizationId($request)).
 */
abstract class ApiController extends Controller
{
    /**
     * Returns the organization ID from the API key authentication context.
     * This is always set by ApiKeyAuthentication middleware.
     */
    protected function organizationId(Request $request): int
    {
        return (int) $request->attributes->get('organization_id');
    }

    /**
     * Resolve `created_by` for API-authenticated requests.
     * API key auth has no associated user; fall back to the org owner or first member.
     */
    protected function resolveCreatedBy(int $orgId): int
    {
        $org = Organization::findOrFail($orgId);
        $owner = $org->members()->wherePivot('role', 'owner')->first()
            ?? $org->members()->first();

        if ($owner === null) {
            abort(422, 'Organization has no members.');
        }

        return $owner->id;
    }
}
