<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->current_organization_id) {
            $firstOrg = $request->user()->organizations()->first();
            if ($firstOrg) {
                $request->user()->update(['current_organization_id' => $firstOrg->id]);
            }
        }

        return $next($request);
    }
}
