<?php

declare(strict_types=1);

namespace App\Domain\LiveMeeting\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * The web side of a satellite invite link.
 *
 * A shared https://note.antara.cloud/live/join/<token> opens the app directly
 * once the app is installed and the platform association below has verified.
 * When it is not — the recipient has no app, or is on desktop — this serves a
 * landing page that offers to open the app and to install it. The custom
 * scheme is kept as the actual door into the app; https is only what makes the
 * link tappable in a chat and gives it somewhere to land otherwise.
 */
class LiveJoinPageController extends Controller
{
    public function show(string $token): View
    {
        $scheme = (string) config('deeplink.scheme');

        return view('live.join', [
            'deepLink' => "{$scheme}://live/join/{$token}",
            'iosStoreUrl' => config('deeplink.ios_store_url'),
            'androidStoreUrl' => config('deeplink.android_store_url'),
        ]);
    }

    /**
     * Apple's association file: which app owns which paths on this domain.
     *
     * Served at /.well-known/apple-app-site-association with a JSON content
     * type and no redirect, both of which Apple requires.
     */
    public function appleAppSiteAssociation(): JsonResponse
    {
        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => [
                    [
                        'appID' => (string) config('deeplink.ios_app_id'),
                        'paths' => ['/live/join/*'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Android's equivalent: the app that may handle this domain's links, keyed
     * to the signing certificate Play holds. Empty fingerprints until the Play
     * App Signing SHA-256 is configured, at which point verification begins.
     */
    public function assetLinks(): JsonResponse
    {
        return response()->json([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => (string) config('deeplink.android_package'),
                    'sha256_cert_fingerprints' => array_values((array) config('deeplink.android_sha256')),
                ],
            ],
        ]);
    }
}
