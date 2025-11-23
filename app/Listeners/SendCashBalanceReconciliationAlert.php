<?php

namespace App\Listeners;

use App\Events\CashBalanceRequiresReconciliation;
use App\Services\WebSocketNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendCashBalanceReconciliationAlert implements ShouldQueue
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
    public function handle(CashBalanceRequiresReconciliation $event): void
    {
        try {
            Log::info('Sending cash balance reconciliation alert', [
                'cash_balance_id' => $event->cashBalance->id,
                'cobrador_id' => $event->cobrador->id,
                'manager_id' => $event->manager?->id,
                'reason' => $event->reason,
            ]);

            $this->wsService->notifyCashBalanceRequiresReconciliation(
                $event->cashBalance,
                $event->cobrador,
                $event->manager,
                $event->reason
            );

            Log::info('Cash balance reconciliation alert sent successfully');
        } catch (\Exception $e) {
            Log::error('Failed to send cash balance reconciliation alert', [
                'error' => $e->getMessage(),
                'cash_balance_id' => $event->cashBalance->id,
            ]);
        }
    }
}
