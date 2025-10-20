<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "reverb", "pusher", "ably", "redis", "log", "null"
    |
    */

    // Si BROADCAST_DRIVER viene como cadena vacía (""), forzamos 'null' para evitar
    // "Broadcast connection [] is not defined" en producción.
    'default'     => env('BROADCAST_DRIVER') ?: 'null',

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'reverb'    => [
            'driver'  => 'reverb',
            'key'     => env('REVERB_APP_KEY'),
            'secret'  => env('REVERB_APP_SECRET'),
            'app_id'  => env('REVERB_APP_ID'),
            'options' => [
                'host'   => env('REVERB_HOST', '127.0.0.1'),
                'port'   => env('REVERB_PORT', 8080),
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
        ],

        'pusher'    => [
            'driver'  => 'pusher',
            'key'     => env('PUSHER_APP_KEY'),
            'secret'  => env('PUSHER_APP_SECRET'),
            'app_id'  => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS'  => true,
            ],
        ],

        'ably'      => [
            'driver' => 'ably',
            'key'    => env('ABLY_KEY'),
        ],

        'redis'     => [
            'driver'     => 'redis',
            'connection' => 'default',
        ],

        'log'       => [
            'driver' => 'log',
        ],

        'null'      => [
            'driver' => 'null',
        ],

        // Configuración personalizada para WebSocket Node.js
        'websocket' => [
            'driver'   => 'websocket',
            'host'     => env('WEBSOCKET_HOST', '192.168.5.44'),
            'port'     => env('WEBSOCKET_PORT', 3001),
            'secure'   => env('WEBSOCKET_SECURE', false),
            'endpoint' => env('WEBSOCKET_ENDPOINT', '/notify'),
        ],

    ],

];
