<?php

declare(strict_types=1);

namespace App\Domain\API\Controllers;

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
}
