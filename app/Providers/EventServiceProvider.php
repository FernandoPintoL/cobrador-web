<?php
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string|string>>
     */
    // NOTE: Event listeners for broadcasting / websocket have been
    // intentionally disabled. If you later re-enable event-based
    // notifications, restore the mappings below.
    /*
    protected $listen = [
        CreditRequiresAttention::class => [
            SendCreditAttentionNotification::class,
        ],
        CreditWaitingListUpdate::class => [
            SendCreditWaitingListNotification::class,
        ],
        PaymentReceived::class => [
            SendPaymentReceivedNotification::class,
        ],
    ];
    */
}
