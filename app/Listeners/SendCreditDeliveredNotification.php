<?php

namespace App\Listeners;

use App\Events\CreditDelivered;
use App\Services\WebSocketNotificationService;
use Illuminate\Support\Facades\Log;

class SendCreditDeliveredNotification
{
    protected WebSocketNotificationService $webSocketService;

    /**
     * Create the event listener.
     */
    public function __construct(WebSocketNotificationService $webSocketService)
    {
        $this->webSocketService = $webSocketService;
    }

    /**
     * Handle the event.
     */
    public function handle(CreditDelivered $event): void
    {
        Log::info('🎧 Listener SendCreditDeliveredNotification ejecutándose', [
            'credit_id' => $event->credit->id,
            'manager_id' => $event->manager->id,
            'cobrador_id' => $event->cobrador->id,
        ]);

        try {
            $this->webSocketService->notifyCreditDelivered(
                $event->credit,
                $event->manager,
                $event->cobrador
            );

            Log::info('✅ Notificación WebSocket de crédito entregado enviada exitosamente');
        } catch (\Exception $e) {
            Log::error('❌ Failed to send credit delivered WebSocket notification', [
                'credit_id' => $event->credit->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
