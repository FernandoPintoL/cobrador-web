<?php

namespace App\Providers;

use App\Events\CashBalanceAutoClosed;
use App\Events\CashBalanceAutoCreated;
use App\Events\CashBalanceRequiresReconciliation;
use App\Events\CreditApproved;
use App\Events\CreditCreated;
use App\Events\CreditDelivered;
use App\Events\CreditRejected;
use App\Events\PaymentCreated;
use App\Listeners\BroadcastStatsUpdatedOnCredit;
use App\Listeners\BroadcastStatsUpdatedOnPayment;
use App\Listeners\SendCashBalanceAutoClosedNotification;
use App\Listeners\SendCashBalanceAutoCreatedNotification;
use App\Listeners\SendCashBalanceReconciliationAlert;
use App\Listeners\SendCreditApprovedNotification;
use App\Listeners\SendCreditCreatedNotification;
use App\Listeners\SendCreditDeliveredNotification;
use App\Listeners\SendCreditRejectedNotification;
use App\Listeners\SendPaymentCreatedNotification;
use App\Listeners\UpdateRealtimeStatsOnCreditApproved;
use App\Listeners\UpdateRealtimeStatsOnCreditCreated;
use App\Listeners\UpdateRealtimeStatsOnCreditDelivered;
use App\Listeners\UpdateRealtimeStatsOnCreditRejected;
use App\Listeners\UpdateRealtimeStatsOnPayment;
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
            UpdateRealtimeStatsOnCreditCreated::class,
            BroadcastStatsUpdatedOnCredit::class . '@handleCreditCreated',
        ],
        CreditApproved::class => [
            SendCreditApprovedNotification::class,
            UpdateRealtimeStatsOnCreditApproved::class,
            BroadcastStatsUpdatedOnCredit::class . '@handleCreditApproved',
        ],
        CreditRejected::class => [
            SendCreditRejectedNotification::class,
            UpdateRealtimeStatsOnCreditRejected::class,
            BroadcastStatsUpdatedOnCredit::class . '@handleCreditRejected',
        ],
        CreditDelivered::class => [
            SendCreditDeliveredNotification::class,
            UpdateRealtimeStatsOnCreditDelivered::class,
            BroadcastStatsUpdatedOnCredit::class . '@handleCreditDelivered',
        ],

        // WebSocket Notifications - Pagos
        PaymentCreated::class => [
            SendPaymentCreatedNotification::class,
            UpdateRealtimeStatsOnPayment::class,
            BroadcastStatsUpdatedOnPayment::class,
        ],

        // WebSocket Notifications - Cash Balances
        CashBalanceAutoClosed::class => [
            SendCashBalanceAutoClosedNotification::class,
        ],
        CashBalanceAutoCreated::class => [
            SendCashBalanceAutoCreatedNotification::class,
        ],
        CashBalanceRequiresReconciliation::class => [
            SendCashBalanceReconciliationAlert::class,
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
