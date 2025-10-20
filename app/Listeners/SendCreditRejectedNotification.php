<?php

namespace App\Listeners;

use App\Events\CreditRejected;
use App\Services\WebSocketNotificationService;
use Illuminate\Support\Facades\Log;

class SendCreditRejectedNotification
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
    public function handle(CreditRejected $event): void
    {
        Log::info('🎧 Listener SendCreditRejectedNotification ejecutándose', [
            'credit_id' => $event->credit->id,
            'manager_id' => $event->manager->id,
            'cobrador_id' => $event->cobrador->id,
        ]);

        try {
            $this->webSocketService->notifyCreditRejected(
                $event->credit,
                $event->manager,
                $event->cobrador
            );

            Log::info('✅ Notificación WebSocket de crédito rechazado enviada exitosamente');
        } catch (\Exception $e) {
            Log::error('❌ Failed to send credit rejected WebSocket notification', [
                'credit_id' => $event->credit->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
