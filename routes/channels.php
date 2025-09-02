<?php

use Illuminate\Support\Facades\Broadcast;

// Enable broadcasting auth using either Sanctum (Bearer) or web session cookies
Broadcast::routes([
    'middleware' => ['auth:sanctum,web'],
]);

// Default Laravel user model channel (kept for compatibility with Echo defaults)
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private user-specific channel used across the app: user.{id}
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Domain channels used by events
Broadcast::channel('credits-attention', function ($user) {
    return method_exists($user, 'hasRole') ? $user->hasAnyRole(['manager', 'cobrador']) : true;
});

Broadcast::channel('waiting-list', function ($user) {
    return method_exists($user, 'hasRole') ? $user->hasAnyRole(['manager', 'cobrador']) : true;
});

Broadcast::channel('payments', function ($user) {
    // Allow any authenticated user to authorize; limit delivery by listeners/events
    return (bool) $user;
});
