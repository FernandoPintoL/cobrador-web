<?php

namespace App\Listeners;

use App\Events\CashBalanceAutoClosed;
use App\Services\WebSocketNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendCashBalanceAutoClosedNotification implements ShouldQueue
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
    public function handle(CashBalanceAutoClosed $event): void
    {
        try {
            Log::info('Sending cash balance auto-closed notification', [
                'cash_balance_id' => $event->cashBalance->id,
                'cobrador_id' => $event->cobrador->id,
                'manager_id' => $event->manager?->id,
            ]);

            $this->wsService->notifyCashBalanceAutoClosed(
                $event->cashBalance,
                $event->cobrador,
                $event->manager
            );

            Log::info('Cash balance auto-closed notification sent successfully');
        } catch (\Exception $e) {
            Log::error('Failed to send cash balance auto-closed notification', [
                'error' => $e->getMessage(),
                'cash_balance_id' => $event->cashBalance->id,
            ]);
        }
    }
}
