<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WebSocket Server URL
    |--------------------------------------------------------------------------
    |
    | La URL del servidor WebSocket Node.js. En producción debe apuntar
    | al servidor desplegado en Railway u otro servicio.
    |
    */
    'url' => env('WEBSOCKET_URL', 'http://localhost:3001'),

    /*
    |--------------------------------------------------------------------------
    | WebSocket Secret Key
    |--------------------------------------------------------------------------
    |
    | Clave secreta para autenticar requests desde Laravel hacia el WebSocket.
    | IMPORTANTE: Debe coincidir con WS_SECRET en el servidor WebSocket.
    |
    */
    'secret' => env('WS_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | WebSocket Enabled
    |--------------------------------------------------------------------------
    |
    | Habilitar o deshabilitar el servicio WebSocket globalmente.
    | Útil para desarrollo o mantenimiento.
    |
    */
    'enabled' => env('WEBSOCKET_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Tiempo máximo de espera (en segundos) para requests HTTP al WebSocket.
    |
    */
    'timeout' => env('WEBSOCKET_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para reintentos automáticos cuando falla el envío.
    |
    */
    'retry' => [
        'max_attempts' => env('WEBSOCKET_MAX_RETRIES', 3),
        'delay_ms' => env('WEBSOCKET_RETRY_DELAY', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para procesamiento asíncrono de notificaciones.
    |
    */
    'queue' => [
        'enabled' => env('WEBSOCKET_QUEUE_ENABLED', false),
        'connection' => env('WEBSOCKET_QUEUE_CONNECTION', 'redis'),
        'name' => env('WEBSOCKET_QUEUE_NAME', 'websocket'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configuración de logs para debugging.
    |
    */
    'logging' => [
        'enabled' => env('WEBSOCKET_LOGGING_ENABLED', true),
        'level' => env('WEBSOCKET_LOG_LEVEL', 'info'), // debug, info, warning, error
    ],
];
