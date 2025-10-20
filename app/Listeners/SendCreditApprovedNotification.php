<?php

namespace App\Listeners;

use App\Events\CreditApproved;
use App\Services\WebSocketNotificationService;
use Illuminate\Support\Facades\Log;

class SendCreditApprovedNotification
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
    public function handle(CreditApproved $event): void
    {
        Log::info('🎧 Listener SendCreditApprovedNotification ejecutándose', [
            'credit_id' => $event->credit->id,
            'manager_id' => $event->manager->id,
            'cobrador_id' => $event->cobrador->id,
        ]);

        try {
            $this->webSocketService->notifyCreditApproved(
                $event->credit,
                $event->manager,
                $event->cobrador,
                $event->entregaInmediata
            );

            Log::info('✅ Notificación WebSocket de crédito aprobado enviada exitosamente');
        } catch (\Exception $e) {
            Log::error('❌ Failed to send credit approved WebSocket notification', [
                'credit_id' => $event->credit->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
