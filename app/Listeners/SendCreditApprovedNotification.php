<?php

namespace App\Listeners;

use App\Events\CreditApproved;
use App\Services\NotificationService;
use App\Services\WebSocketNotificationService;
use Illuminate\Support\Facades\Log;

class SendCreditApprovedNotification
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
    public function handle(CreditApproved $event): void
    {
        Log::info('🎧 Listener SendCreditApprovedNotification ejecutándose', [
            'credit_id' => $event->credit->id,
            'manager_id' => $event->manager->id,
            'cobrador_id' => $event->cobrador->id,
        ]);

        try {
            // 1. Guardar notificación en DB
            Log::info('💾 Guardando notificación de crédito aprobado en DB...');

            $this->notificationService->createCreditApprovedNotification(
                $event->credit,
                $event->manager,
                $event->cobrador,
                $event->entregaInmediata
            );

            Log::info('✅ Notificación de crédito aprobado guardada en DB exitosamente');
        } catch (\Exception $e) {
            Log::error('❌ Failed to save credit approved notification to database', [
                'credit_id' => $event->credit->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            // 2. Enviar por WebSocket
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
