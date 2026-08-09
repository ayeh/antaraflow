<?php

declare(strict_types=1);

namespace App\Domain\API\Middleware\Mobile;

use App\Domain\Admin\Models\PlatformSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects app builds that are too old to talk to this API.
 *
 * Clients send `X-Client-Version: ios/1.4.2 (build 210)`. The minimum is held
 * in platform settings so a broken release can be cut off without shipping
 * anything, and every response advertises the current floor so the app can warn
 * before it is actually blocked.
 */
class EnsureClientVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('X-Client-Version', '');
        [$platform, $version] = $this->parse($header);

        $minimum = $this->minimumFor($platform);

        if ($minimum !== null && $version !== null && version_compare($version, $minimum, '<')) {
            return response()->json([
                'message' => __('Please update antaraFlow to continue.'),
                'code' => 'CLIENT_UPGRADE_REQUIRED',
                'minimum_version' => $minimum,
                'store_url' => $this->storeUrl($platform),
            ], 426);
        }

        $response = $next($request);

        if ($minimum !== null) {
            $response->headers->set('X-Min-Client-Version', $minimum);
        }

        return $response;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function parse(string $header): array
    {
        if (! preg_match('#^(ios|android)/(\d+(?:\.\d+)*)#i', trim($header), $matches)) {
            return [null, null];
        }

        return [strtolower($matches[1]), $matches[2]];
    }

    private function minimumFor(?string $platform): ?string
    {
        if ($platform === null) {
            return null;
        }

        $value = PlatformSetting::getValue("mobile_min_version_{$platform}");

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function storeUrl(?string $platform): ?string
    {
        return match ($platform) {
            'ios' => 'https://apps.apple.com/app/antaranote',
            'android' => 'https://play.google.com/store/apps/details?id=cloud.antara.note',
            default => null,
        };
    }
}
