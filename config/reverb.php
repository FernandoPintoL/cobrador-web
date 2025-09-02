<?php

return [
    'apps' => [
        [
            'key' => env('REVERB_APP_KEY', 'reverb-app-key'),
            'secret' => env('REVERB_APP_SECRET', 'reverb-app-secret'),
            'app_id' => env('REVERB_APP_ID', 'cobrador-app'),
        ],
    ],

    'server' => [
        // For Railway, listen on 0.0.0.0 and dynamic PORT when provided
        'host' => env('REVERB_HOST', '127.0.0.1'),
        'port' => env('REVERB_PORT', 8080),
        'debug' => env('REVERB_DEBUG', false),
        'max_request_size' => 10_000_000,
    ],

    'clients' => [
        'use_tls' => env('REVERB_SCHEME', 'http') === 'https',
        // Allow origins from env (comma-separated) or fallback to APP_URL
        'allowed_origins' => array_filter(array_map('trim', explode(',', (string) env('REVERB_ALLOWED_ORIGINS', env('APP_URL'))))),
    ],
];
