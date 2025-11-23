<?php

namespace App\Listeners;

use App\Events\CashBalanceAutoCreated;
use App\Services\WebSocketNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendCashBalanceAutoCreatedNotification implements ShouldQueue
{
    protected WebSocketNotificationService $wsService;

    /**
     * Create the event listener.
     */
    public function __construct(WebSocketNotificationService $wsService)
    {
        $this->wsService = $wsService;
    }

    /**
     * Handle the event.
     */
    public function handle(CashBalanceAutoCreated $event): void
    {
        try {
            Log::info('Sending cash balance auto-created notification', [
                'cash_balance_id' => $event->cashBalance->id,
                'cobrador_id' => $event->cobrador->id,
                'manager_id' => $event->manager?->id,
                'reason' => $event->reason,
            ]);

            $this->wsService->notifyCashBalanceAutoCreated(
                $event->cashBalance,
                $event->cobrador,
                $event->manager,
                $event->reason
            );

            Log::info('Cash balance auto-created notification sent successfully');
        } catch (\Exception $e) {
            Log::error('Failed to send cash balance auto-created notification', [
                'error' => $e->getMessage(),
                'cash_balance_id' => $event->cashBalance->id,
            ]);
        }
    }
}
