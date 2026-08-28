<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | App links / universal links
    |--------------------------------------------------------------------------
    |
    | These associate this domain with the mobile apps so an
    | https://note.antara.cloud/live/join/<token> link opens the app directly
    | (and this site's landing page is the fallback when it is not installed).
    |
    */

    'scheme' => 'antaranote',

    // TeamID.BundleID — the value Apple matches against the app's entitlement.
    'ios_app_id' => env('DEEPLINK_IOS_APP_ID', 'YGDAPZRD8V.cloud.antara.note'),

    'android_package' => env('DEEPLINK_ANDROID_PACKAGE', 'cloud.antara.note'),

    // SHA-256 fingerprints of the Play App Signing certificate (Google re-signs
    // on Play), comma separated. Until this is set, Android App Links cannot
    // verify and the https link falls back to the landing page on Android.
    'android_sha256' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DEEPLINK_ANDROID_SHA256', '')),
    ))),

    'ios_store_url' => env('DEEPLINK_IOS_STORE_URL', 'https://apps.apple.com/app/antaranote'),

    'android_store_url' => env(
        'DEEPLINK_ANDROID_STORE_URL',
        'https://play.google.com/store/apps/details?id=cloud.antara.note',
    ),
];
