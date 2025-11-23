<?php

namespace App\Listeners;

use App\Events\CreditRejected;
use App\Services\NotificationService;
use App\Services\WebSocketNotificationService;
use Illuminate\Support\Facades\Log;

class SendCreditRejectedNotification
{
    protected WebSocketNotificationService $webSocketService;
    protected NotificationService $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(
        WebSocketNotificationService $webSocketService,
        NotificationService $notificationService
    ) {
        $this->webSocketService = $webSocketService;
        $this->notificationService = $notificationService;
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
            // 1. Guardar notificación en DB
            Log::info('💾 Guardando notificación de crédito rechazado en DB...');

            $this->notificationService->createCreditRejectedNotification(
                $event->credit,
                $event->manager,
                $event->cobrador
            );

            Log::info('✅ Notificación de crédito rechazado guardada en DB exitosamente');
        } catch (\Exception $e) {
            Log::error('❌ Failed to save credit rejected notification to database', [
                'credit_id' => $event->credit->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            // 2. Enviar por WebSocket
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
