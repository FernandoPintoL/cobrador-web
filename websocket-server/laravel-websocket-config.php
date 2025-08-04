<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WebSocket Configuration
    |--------------------------------------------------------------------------
    |
    | Configuración para el servidor WebSocket de la aplicación Cobrador
    |
    */

    'url' => env('WEBSOCKET_URL', 'http://localhost:3001'),
    
    'enabled' => env('WEBSOCKET_ENABLED', true),
    
    'timeout' => env('WEBSOCKET_TIMEOUT', 5),
    
    'retry_attempts' => env('WEBSOCKET_RETRY_ATTEMPTS', 3),
    
    'events' => [
        'credit_notification' => 'new_credit_notification',
        'payment_update' => 'payment_updated',
        'route_update' => 'route_updated',
        'credit_attention' => 'credit_attention_required',
        'user_message' => 'new_message',
        'location_update' => 'cobrador_location_update',
    ],
    
    'user_types' => [
        'client' => 'client',
        'cobrador' => 'cobrador',
        'admin' => 'admin',
    ],
];
