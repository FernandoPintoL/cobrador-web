<?php

namespace App\Providers;

use App\Events\CreditApproved;
use App\Events\CreditCreated;
use App\Events\CreditDelivered;
use App\Events\CreditRejected;
use App\Events\PaymentCreated;
use App\Listeners\SendCreditApprovedNotification;
use App\Listeners\SendCreditCreatedNotification;
use App\Listeners\SendCreditDeliveredNotification;
use App\Listeners\SendCreditRejectedNotification;
use App\Listeners\SendPaymentCreatedNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string|string>>
     */
    protected $listen = [
        // WebSocket Notifications - Ciclo de vida de créditos
        CreditCreated::class => [
            SendCreditCreatedNotification::class,
        ],
        CreditApproved::class => [
            SendCreditApprovedNotification::class,
        ],
        CreditRejected::class => [
            SendCreditRejectedNotification::class,
        ],
        CreditDelivered::class => [
            SendCreditDeliveredNotification::class,
        ],

        // WebSocket Notifications - Pagos
        PaymentCreated::class => [
            SendPaymentCreatedNotification::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
