<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_CALENDAR_REDIRECT_URI'),
    ],
    'outlook' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect_uri' => env('MICROSOFT_CALENDAR_REDIRECT_URI'),
        'tenant_id' => env('MICROSOFT_TENANT_ID', 'common'),
    ],
];
