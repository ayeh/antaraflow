<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => '/auth/google/callback',
    ],

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => '/auth/microsoft/callback',
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => '/auth/github/callback',
    ],

    'mydigitalid' => [
        'client_id' => env('MYDIGITALID_CLIENT_ID'),
        'client_secret' => env('MYDIGITALID_CLIENT_SECRET'),
        'redirect' => '/auth/mydigitalid/callback',
        'base_url' => env('MYDIGITALID_BASE_URL', 'https://sso.digital-id.my/oidc'),
        'authorize_uri' => env('MYDIGITALID_AUTHORIZE_URI'),
        'token_uri' => env('MYDIGITALID_TOKEN_URI'),
        'userinfo_uri' => env('MYDIGITALID_USERINFO_URI'),
        'scopes' => explode(' ', (string) env('MYDIGITALID_SCOPES', 'openid profile email')),
        'pkce' => env('MYDIGITALID_PKCE', true),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials' => env('FCM_CREDENTIALS_PATH', storage_path('app/private/fcm-service-account.json')),
    ],

];
