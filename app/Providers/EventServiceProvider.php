<?php

namespace App\Providers;

use App\Events\CreditRequiresAttention;
use App\Events\CreditWaitingListUpdate;
use App\Events\PaymentReceived;
use App\Listeners\SendCreditAttentionNotification;
use App\Listeners\SendCreditWaitingListNotification;
use App\Listeners\SendPaymentReceivedNotification;
use App\Listeners\WebSocketNotificationListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string|string>>
     */
    protected $listen = [
        CreditRequiresAttention::class => [
            SendCreditAttentionNotification::class,
            // Centralized WebSocket forwarder (HTTP bridge for external WS if used)
            WebSocketNotificationListener::class.'@handleCreditRequiresAttention',
        ],
        CreditWaitingListUpdate::class => [
            SendCreditWaitingListNotification::class,
            WebSocketNotificationListener::class.'@handleCreditWaitingListUpdate',
        ],
        PaymentReceived::class => [
            SendPaymentReceivedNotification::class,
            WebSocketNotificationListener::class.'@handlePaymentReceived',
        ],
    ];
}
