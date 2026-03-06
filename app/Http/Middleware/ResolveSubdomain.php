<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Account\Models\ResellerSetting;
use App\Domain\Admin\Services\BrandingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveSubdomain
{
    public function __construct(
        private BrandingService $brandingService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $appDomain = config('app.domain', 'antaraflow.test');

        if ($host === $appDomain || $host === 'localhost' || $host === '127.0.0.1') {
            return $next($request);
        }

        try {
            $resellerSetting = $this->resolveBySubdomain($host)
                ?? $this->resolveByCustomDomain($host);
        } catch (\Throwable) {
            return $next($request);
        }

        if ($resellerSetting) {
            $request->attributes->set('reseller_organization', $resellerSetting->organization);
            $request->attributes->set('reseller_setting', $resellerSetting);

            $this->brandingService->setOrganizationOverrides($resellerSetting->branding_overrides);
        }

        return $next($request);
    }

    private function resolveByCustomDomain(string $host): ?ResellerSetting
    {
        return ResellerSetting::query()
            ->where('custom_domain', $host)
            ->where('is_reseller', true)
            ->with('organization')
            ->first();
    }

    private function resolveBySubdomain(string $host): ?ResellerSetting
    {
        $appDomain = config('app.domain', 'antaraflow.test');
        $subdomain = $this->extractSubdomain($host, $appDomain);

        if (! $subdomain) {
            return null;
        }

        return ResellerSetting::query()
            ->where('subdomain', $subdomain)
            ->where('is_reseller', true)
            ->with('organization')
            ->first();
    }

    private function extractSubdomain(string $host, string $appDomain): ?string
    {
        if (! str_ends_with($host, ".{$appDomain}")) {
            return null;
        }

        $subdomain = str_replace(".{$appDomain}", '', $host);

        if ($subdomain === '' || $subdomain === $host) {
            return null;
        }

        return $subdomain;
    }
}
